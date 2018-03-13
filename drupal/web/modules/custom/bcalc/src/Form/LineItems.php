<?php

namespace Drupal\bcalc\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Class LineItems.
 */
class LineItems extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'line_items';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $year_month = null) {

    if ($year_month == NULL) {
      return $form;
    }

    $datepieces = explode('-', $year_month);
    $year = $datepieces[0];
    $month = $datepieces[1];
    $beginning_of_month = $year_month . '-01';
    $end_of_month = $year_month . '-' . cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $dateObj = \DateTime::createFromFormat('!m', $month);
    $monthName = $dateObj->format('F');

    $form['#title'] = "{$monthName} {$year}";

    $header = [
      'Date',
      'Type',
      'Source',
      'Amount',
      'Category'
    ];

    $form['line_items_table'] = [
      '#type' => 'table',
      '#header' => $header,
    ];

    $tt = \Drupal::service('taxonomy_tree.taxonomy_term_tree');
    $terms = $tt->load('category');

    $options = [''];
    foreach ($terms as $parent) {
      foreach ($parent->children as $child) {
        $options[$parent->name][$child->tid] = $child->name;
      }
    }

    //$return = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => 'Return']);
    //$payment = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => 'Payment']);
    //$return_term = array_shift($return);
    //$payment_term = array_shift($payment);

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'line_item')
      ->condition('field_trans_date', [
        $beginning_of_month,
        $end_of_month
      ], 'BETWEEN')
      //->condition('field_transaction', [$return_term->id(),$payment_term->id()], 'NOT IN')
      ->condition('uid', \Drupal::currentUser()->id())
      ->sort('field_transaction')
      ->execute();
    $nodes = Node::loadMultiple($nids);

    foreach ($nodes as $node) {

      $node_id = $node->id();
      $cat_id = $node->get('field_category')->target_id;
      $src_id = $node->get('field_source')->target_id;
      $trns_id = $node->get('field_transaction')->target_id;

      $form['line_items_table'][$node_id]['date'] = [
        '#type' => 'markup',
        '#markup' => $node->get('field_trans_date')->value
      ];
      $form['line_items_table'][$node_id]['type'] = [
        '#type' => 'markup',
        '#markup' => ($trns_id ? Term::load($trns_id)->getName() : '')
      ];
      $form['line_items_table'][$node_id]['source'] = [
        '#type' => 'markup',
        '#markup' => ($src_id ? Term::load($src_id)
          ->getName() : $node->get('title')->getString())
      ];
      $form['line_items_table'][$node_id]['amount'] = [
        '#type' => 'markup',
        '#markup' => '$' . $node->get('field_amount')->value
      ];
      $form['line_items_table'][$node_id]['category'] = [
        '#type' => 'select',
        '#options' => $options,
        '#default_value' => $cat_id,
      ];
      $form['line_items_table'][$node_id]['edit'] = [
        '#type' => 'markup',
        '#markup' => Link::createFromRoute('Edit', 'entity.node.edit_form', ['node' => $node_id], ['query' => ['destination' => '/bcalc/line-items/edit/' . $year_month]])
          ->toString(),
      ];

    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
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
    $form_values = $form_state->getValues();
    foreach ($form_values['line_items_table'] as $key => $value) {

      //LOAD NODE
      $node = Node::load($key);

      //SET CATEGORY
      $node->set('field_category', ['target_id' => $value['category']]);

      //CHECK THAT SOURCE USES SAME CATEGORY FOR IMPORT CHECK
      if($source_tid = $node->get('field_source')->target_id) {
        $source_term = Term::load($source_tid);
        if ($source_category_tid = $source_term->get('field_category')->target_id) {
          if ($source_category_tid != $value['category']) {
            //IF THE SOURCE CATEGORY IS DIFFERENT, CHANGE IT
            $source_term->set('field_category', $value['category']);
            $source_term->save();
          }
        }
      }

      //SAVE NODE
      $node->save();
    }


  }

}
