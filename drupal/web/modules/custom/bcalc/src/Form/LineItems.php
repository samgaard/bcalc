<?php

namespace Drupal\bcalc\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy_tree\TaxonomyTermTree;

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
  public function buildForm(array $form, FormStateInterface $form_state, $nids = []) {

    $tt = \Drupal::service('taxonomy_tree.taxonomy_term_tree');
    $terms = $tt->load('category');

    $options = [];
    foreach($terms as $parent) {
      foreach ($parent->children as $child) {
        $options[$parent->name][$child->tid] = $child->name;
      }
    }

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => $options,
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
    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      drupal_set_message($key . ': ' . $value);
    }

  }

}
