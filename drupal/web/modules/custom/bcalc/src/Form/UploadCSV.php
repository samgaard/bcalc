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
    $fid = $form_state->getValue('csv_file');

    $file = \Drupal::entityTypeManager()->getStorage('file')->load($fid[0]);

    $header = NULL;
    if (($handle = fopen($file->getFileUri(), "r")) !== FALSE) {
      while (($row = fgetcsv($handle, 100000, ",")) !== FALSE) {
        if ($header === NULL) {
          $header = $row;
          break;
        }
      }
    }

    //VALIDATE FILE FORMAT
    if (!$this->validate_header($header)) {
      $form_state->setError($form['csv_file'], 'Your file needs to match an approved format.');
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    if ($file = $form_state->getValue('csv_file')) {

      $this->import_from_csv($file[0]);

    }

  }

  private function valid_headers() {
    return [
      'chase_cc_statement' => [
        'Type',
        'Trans Date',
        'Post Date',
        'Description',
        'Amount'
      ],
      'chase_checking' => [
        'Details',
        'Posting Date',
        'Description',
        'Amount',
        'Type',
        'Balance',
        'Check or Slip #',
      ]
    ];
  }

  private function validate_header($header = []) {
    //CONFIRM DOCUMENT IS IN ONE OF THE APPROVED FORMATS
    foreach($this->valid_headers() as $header_type => $valid_header) {
      if(!array_diff($valid_header, $header)) {
        //RETURN HEADER TYPE. USEFUL FOR DETERMINING HOW TO IMPORT DATA.
        return $header_type;
      }
    }
    //DEFAULT RETURN FALSE
    return false;
  }

  private function import_from_csv($fid) {

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

    //GET FILE FORMAT
    if ($file_format = $this->validate_header($header)) {

      //LOOP THROUGH ALL ITEMS
      foreach ($lineitems AS $lineitem) {

        //INTERPRET DATA BASED ON FILE FORMAT
        switch ($file_format) {
          case 'chase_cc_statement':
            $transaction_description = $lineitem['Description'];
            $transaction_type = $lineitem['Type'];
            $transaction_date = $lineitem['Trans Date'];
            $transaction_amount = $lineitem['Amount'];
            break;
          case 'chase_checking':
            $transaction_type = '';
            switch($lineitem['Details']) {
              case 'DEBIT':
                $transaction_type = 'Money Out';
                break;
              case 'CREDIT':
              case 'DSLIP':
                $transaction_type = 'Money In';
                break;
            }
            $transaction_description = $lineitem['Description'];
            $transaction_date = $lineitem['Posting Date'];
            $transaction_amount = $lineitem['Amount'];

            if(strpos($transaction_description, 'Payment to Chase') !== false) {
              continue 2;
            }

            break;
        }

        // Create node object.
        $node = Node::create([
          'type' => 'line_item',
          'title' => $transaction_description . ' - ' . time(),
        ]);

        //field_transaction
        $txn_term_name = $transaction_type;
        $term = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->loadByProperties(['name' => $txn_term_name]);
        $txn_tid = array_keys($term);

        if (isset($txn_tid[0])) {
          $node->set('field_transaction', ['target_id' => $txn_tid[0]]);
        }

        //dates
        $node->set('field_trans_date', date('Y-m-d', strtotime($transaction_date)));

        //amount
        $node->set('field_amount', abs($transaction_amount));

        //check if exists
        $desc_term_name = $transaction_description;
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

        drupal_set_message('Created line item for ' . $node->getTitle());

      }

    }

    unlink($file->getFileUri());

  }

}