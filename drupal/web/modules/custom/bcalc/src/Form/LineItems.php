<?php

namespace Drupal\bcalc\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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
  public function buildForm(array $form, FormStateInterface $form_state, $year_month = '2017-07') {

    $header = [
      'Date',
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
    foreach($terms as $parent) {
      foreach ($parent->children as $child) {
        $options[$parent->name][$child->tid] = $child->name;
      }
    }

    $datepieces = explode('-', $year_month);
    $year = $datepieces[0];
    $month = $datepieces[1];
    $beginning_of_month = $year_month . '-01';
    $end_of_month = $year_month . '-' . cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $nids = \Drupal::entityQuery('node')
      ->condition('type','line_item')
      ->condition('field_trans_date', [$beginning_of_month, $end_of_month], 'BETWEEN')
      ->condition('uid', \Drupal::currentUser()->id())
      ->execute();
    $nodes = Node::loadMultiple($nids);

    foreach($nodes as $node) {

      $node_id = $node->id();
      $cat_id = $node->get('field_category')->target_id;
      $src_id = $node->get('field_source')->target_id;

      $form['line_items_table'][$node_id]['date'] = [
        '#type' => 'markup',
        '#markup' => $node->get('field_trans_date')->value
      ];
      $form['line_items_table'][$node_id]['source'] = [
        '#type' => 'markup',
        '#markup' => ($src_id ? Term::load($src_id)->getName() : '')
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
     if($value['category']) {
       $node = Node::load($key);
       $node->set('field_category', ['target_id' => $value['category']]);
       $node->save();
     }
    }

  }

}
