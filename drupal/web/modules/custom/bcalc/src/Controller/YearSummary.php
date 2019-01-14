<?php

namespace Drupal\bcalc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\bcalc\BcalcHelper;

/**
 * Class YearSummary.
 */
class YearSummary extends ControllerBase {

  /**
   * @return array
   */

  public function build() {
    $bcalc = new BcalcHelper();

    $year = isset($_REQUEST['year']) && is_numeric($_REQUEST['year']) ? $_REQUEST['year'] : date('Y');

    $yearly_summary_chart = $bcalc->buildYearSummaryChart($year);

    $yearly_summary_averages = $bcalc->buildYearSummaryAveragesTable($year);

    return [
      '#theme' => 'year-summary',
      '#yearly_summary_chart' => $yearly_summary_chart,
      '#yearly_summary_averages' => $yearly_summary_averages,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
