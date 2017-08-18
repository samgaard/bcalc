<?php

namespace Drupal\bcalc\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'MoreQuestions' block.
 *
 * @Block(
 *  id = "more_questions",
 *  admin_label = @Translation("More questions sidebar"),
 * )
 */
class MoreQuestions extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['more_questions']['#markup'] = 'Implement MoreQuestions.';

    return $build;
  }

}
