<?php

namespace Drupal\bcalc\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class Homepage.
 */
class Homepage extends ControllerBase {

  /**
   * @return array
   */
  public function home() {

    return ['#markup' => $this->t('This is the homepage.')];

  }

}

