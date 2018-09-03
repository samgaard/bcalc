<?php

namespace Drupal\bcalc\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
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
  public function buildForm(array $form, FormStateInterface $form_state, $year_month = NULL) {

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
      'Category',
      'Edit',
      'Delete'
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
        if(isset($child->children) && count($child->children)) {
          foreach ($child->children as $baby_child) {
            $options[$parent->name][$baby_child->tid] = $baby_child->name;
          }
        } else {
          $options[$parent->name][$child->tid] = $child->name;
        }
      }
    }

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'line_item')
      ->condition('field_trans_date', [
        $beginning_of_month,
        $end_of_month
      ], 'BETWEEN')
      ->condition('uid', \Drupal::currentUser()->id())
      ->sort('field_trans_date')
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
      $form['line_items_table'][$node_id]['delete'] = [
        'data' => ['#type' => 'checkbox'],
        '#wrapper_attributes' => ['class' => ['text-center']],
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
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();

    $dblog_message = '';

    foreach ($form_values['line_items_table'] as $key => $value) {

      //LOAD NODE
      $node = Node::load($key);

      $current_category = $node->get('field_category')->target_id;

      if($value['delete']['data'] > 0 || $current_category != $value['category']) {

        $source_id = $node->get('field_source')->target_id;
        if ($source_id > 0) {
          $source_name = Term::load($source_id)->label();
        }
        else {
          $source_name = $node->label();
        }

        if ($value['delete']['data'] > 0) {
          //set message
          $log_message = "Deleted line item {$source_name} ({$node->id()}).";
          \Drupal::messenger()->addMessage($log_message);
          $dblog_message .= $log_message . '<br />';
          $node->delete();
          continue;
        }

        //CHANGED LINE ITEM CATEGORY
        if ($current_category != $value['category']) {

          if ($value['category'] > 0) {
            $term = Term::load($value['category']);
          }
          if ($current_category > 0) {
            $old_term = Term::load($current_category);
            if ($value['category'] == 0) {
              $log_message = "Removed category {$old_term->label()} FROM \"{$source_name} ({$node->id()})\".";
            }
            else {
              $log_message = "Changed \"{$source_name} ({$node->id()})\" FROM {$old_term->label()} TO {$term->label()}.";
            }
          }
          else {
            $log_message = "Added category {$term->label()} TO \"{$source_name} ({$node->id()})\".";
          }
          \Drupal::messenger()->addMessage($log_message);
          $dblog_message .= $log_message . '<br />';

          //SET CATEGORY
          $node->set('field_category', ['target_id' => $value['category']]);
          //SAVE NODE
          $node->save();
        }

        if ($value['category'] > 0) {
          //CHECK THAT SOURCE USES SAME CATEGORY FOR IMPORT CHECK
          if ($source_tid = $node->get('field_source')->target_id) {
            $source_term = Term::load($source_tid);
            $source_category_tid = $source_term->get('field_category')->target_id;
            if ($source_category_tid != $value['category']) {
              //IF THE SOURCE CATEGORY IS DIFFERENT, CHANGE IT
              $source_term->set('field_category', $value['category']);
              $source_term->save();
            }
          }
        }
      }
    }

    if ($dblog_message != '') {
      \Drupal::logger('line_items')->notice($dblog_message);
    }
  }

}
