<?php

namespace Drupal\bcalc\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Class UploadCSV.
 */
class UploadCSV extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'upload_csv';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $validators = [
      'file_validate_extensions' => ['csv'],
    ];

    //PROVIDE CSV UPLOAD INTERFACE
    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#upload_location' => 'public://my_files/',
      '#upload_validators' => $validators,
      '#title' => $this->t('CSV File'),
      '#description' => $this->t('The file exported from your banking website'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    if ($file = $form_state->getValue('csv_file')) {

      import_from_csv($file[0]);

    }

  }

}

function import_from_csv($fid) {

  $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid);

  $lineitems = [];
  $header = NULL;
  if (($handle = fopen($file->getFileUri(), "r")) !== FALSE) {
    while (($row = fgetcsv($handle, 100000, ",")) !== FALSE) {
      if ($header === NULL) {
        $header = $row;
        continue;
      }
      $lineitems[] = array_combine($header, $row);
    }
  }

  foreach ($lineitems AS $lineitem) {
    // Create node object.
    $node = Node::create([
      'type' => 'line_item',
      'title' => $lineitem['Description'] . ' - ' . time(),
    ]);

    //field_transaction
    $txn_term_name = $lineitem['Type'];
    $term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $txn_term_name]);
    $txn_tid = array_keys($term);

    if (isset($txn_tid[0])) {
      $node->set('field_transaction', ['target_id' => $txn_tid[0]]);
    }

    //dates
    $node->set('field_trans_date', date('Y-m-d', strtotime($lineitem['Trans Date'])));
    $node->set('field_post_date', date('Y-m-d', strtotime($lineitem['Post Date'])));

    //amount
    $node->set('field_amount', abs($lineitem['Amount']));

    //check if exists
    $desc_term_name = $lineitem['Description'];
    $term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $desc_term_name]);
    $desc_tid = array_keys($term);
    if (!isset($desc_tid[0])) {
      //create new
      $new_term = Term::create([
        'name' => $desc_term_name,
        'vid' => 'source',
      ]);
      $new_term->save();

      $term = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties(['name' => $desc_term_name]);
      $desc_tid = array_keys($term);
    }
    $node->set('field_source', ['target_id' => $desc_tid[0]]);

    //IF THE SOURCE HAS A CATEGORY, USE IT FOR LINE ITEM NODE
    $source_tid = $desc_tid[0];
    $source_term = Term::load($source_tid);
    $source_category_tid = $source_term->get('field_category')->target_id;
    if ($source_category_tid != '') {
      //IF THE SOURCE SOURCE HAS A CATEGORY, USE IT
      $node->set('field_category', ['target_id' => $source_category_tid]);
    }

    $node->setOwnerId(\Drupal::currentUser()->id());

    $node->save();


  }

  unlink($file->getFileUri());

}