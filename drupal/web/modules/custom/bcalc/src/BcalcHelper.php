<?php

namespace Drupal\bcalc;

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class Homepage.
 */
class BcalcHelper {

  public function buildSummaryPieChart($year_month, $income = FALSE) {

    list($categories, $numbers) = $this->getMonthlyStats($year_month, $income);

    return $this->buildChart($categories, $numbers, 'Series ' . $year_month);
  }

  public function buildYearSummaryChart($year = NULL) {

    // lookup stats
    list($serieses, $categories, $active_months) = $this->getYearlyStats($year);

    $options = [];

    $options['type'] = 'line';
    $options['data_labels'] = ['test'];
    $options['yaxis_title'] = t('Y-Axis');
    $options['yaxis_min'] = '';
    $options['data_markers'] = '';
    $options['yaxis_max'] = '';
    $options['xaxis_labels_rotation'] = 0;
    $options['xaxis_title'] = t('X-Axis');
    $options['three_dimensional'] = FALSE;
    $options['title_position'] = 'out';
    $options['legend_position'] = 'right';

    // Creates a UUID for the chart ID.
    $uuid_service = \Drupal::service('uuid');
    $chartId = 'chart-' . $uuid_service->generate();

    return [
      '#theme' => 'charts_api_example',
      '#library' => 'highcharts',
      '#categories' => $categories,
      '#seriesData' => $serieses,
      '#options' => $options,
      '#id' => $chartId,
    ];
  }

  public function buildYearSummaryAverages($year = NULL) {
    if (!$year) {
      $year = date('Y');
    }

    list($serieses, $categories, $active_months) = $this->getYearlyStats($year);

    $rows = [];

    foreach ($serieses as $series) {
      $row = [];

      $row[] = $series['name'];

      $total = 0;
      if (isset($series['data'])) {
        foreach ($series['data'] as $month_total) {
          $total += $month_total;
        }
      }
      $row[] = $total;

      $row[] = ($total ? number_format($total / $active_months, 2) : 0);

      if ($total) {
        $rows[] = $row;
      }
    }

    $header = ['Name', 'Total', 'Average (' . $active_months . ' months)'];

    $list = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
    return $list;

  }

  public function buildChart($categories = [], $numbers = [], $series_name = '', $chart_type = 'pie') {
    $seriesData = [];
    $options = [];
    $options['title'] = '';

    $seriesData[] = [
      'name' => $series_name,
      'color' => '#8bbc21',
      'type' => $chart_type,
      "data" => $numbers,
    ];

    $options['type'] = $chart_type;
    $options['data_labels'] = ['test'];
    $options['yaxis_title'] = t('Y-Axis');
    $options['yaxis_min'] = '';
    $options['yaxis_max'] = '';
    $options['xaxis_labels_rotation'] = 0;
    $options['xaxis_title'] = t('X-Axis');
    $options['three_dimensional'] = FALSE;
    $options['title_position'] = 'out';
    $options['legend_position'] = 'right';

    // Creates a UUID for the chart ID.
    $uuid_service = \Drupal::service('uuid');
    $chartId = 'chart-' . $uuid_service->generate();

    return [
      '#theme' => 'charts_api_example',
      '#library' => 'highcharts',
      '#categories' => $categories,
      '#seriesData' => $seriesData,
      '#options' => $options,
      '#id' => $chartId,
    ];
  }

  // private functions
  private function getYearlyStats($year = NULL) {
    if (!$year) {
      $year = date('Y');
    }

    //    $cache_id = 'yearlystats.' . $year . \Drupal::currentUser()->id();
    //
    //    $yearly_stats = \Drupal::cache()->get($cache_id);
    //    if (!empty($yearly_stats)) {
    //      return $yearly_stats->data;
    //    }

    $seriesData = [];
    $categories = [];

    $tt = \Drupal::service('taxonomy_tree.taxonomy_term_tree');
    $terms = $tt->load('category');
    foreach ($terms as $parent) {
      $seriesData[$parent->name] = ['name' => $parent->name];
    }

    $active_months = 0;

    //month names and data
    for ($i = 1; $i < 13; $i++) {
      $found_data = FALSE;

      //INCOME
      $monthlyIncomeStats = $this->getMonthlyStats($year . sprintf("%02d", $i), TRUE);
      $incomeTotal = 0;
      if (count($monthlyIncomeStats[1])) {
        $incomeTotal = 0;
        foreach ($monthlyIncomeStats[1] as $cat) {
          $incomeTotal += $cat['y'];
        }
      }
      if ($incomeTotal) {
        $seriesData['Income']['data'][] = $incomeTotal;
        $found_data = TRUE;
      }

      //SPENDING
      $monthlyStats = $this->getMonthlyStats($year . sprintf("%02d", $i));
      if (count($monthlyStats[1])) {
        $month_catdata = [];
        foreach ($monthlyStats[1] as $cat) {
          $month_catdata[$cat['name']]['total'] = $cat['y'];
        }
        foreach ($seriesData as $catname => $value) {
          if ($catname != 'Income') {
            $total = in_array($catname, $monthlyStats[0]) ? $month_catdata[$catname]['total'] : 0;
            $seriesData[$catname]['data'][] = $total;
            $found_data = TRUE;
          }
        }
      }

      if ($found_data) {
        $categories[] = date("F", mktime(NULL, NULL, NULL, $i, 1));
        $active_months++;
      }

    }

    //filter out series data with all zeros?

    $serieses = [];
    foreach ($seriesData as $val) {
      $serieses[] = $val;
    }

    $year_nums = [$serieses, $categories, $active_months];

    //cache results
    //\Drupal::cache()->set($cache_id, $year_nums, $active_months);

    return $year_nums;
  }

  private function getMonthlyStats($year_month, $income = FALSE) {

    $cache_id = 'monthlystats.'
      . $year_month
      . '.' . ($income ? 'income' : 'spending')
      . '.' . \Drupal::currentUser()->id();

    $monthly_stats = \Drupal::cache()->get($cache_id);
    if (!empty($monthly_stats)) {
      return $monthly_stats->data;
    }

    $connection = Database::getConnection();

    $query = "";

    if ($income) {
      $query .= "SELECT MIN(parent_term.name) AS parent_name, catdata.name AS cat_name,";
    }
    else {
      $query .= "SELECT parent_term.name AS parent_name,";
    }

    $query .= " SUM(amount.field_amount_value) AS amount, 
    MIN(nfd.nid) AS nid, 
    MIN(catdata.tid) AS cat_tid, 
    MIN(parent_term.tid) AS parent_tid
    FROM 
    {node_field_data} nfd
    LEFT JOIN {node__field_category} nfc ON nfd.nid = nfc.entity_id
    LEFT JOIN {taxonomy_term_field_data} catdata ON nfc.field_category_target_id = catdata.tid
    LEFT JOIN {taxonomy_term__parent} h ON catdata.tid = h.entity_id
    LEFT JOIN {taxonomy_term_field_data} parent_term ON h.parent_target_id = parent_term.tid
    LEFT JOIN {node__field_amount} amount ON nfd.nid = amount.entity_id
    LEFT JOIN {node__field_trans_date} transdate ON nfd.nid = transdate.entity_id 
    WHERE ((DATE_FORMAT(transdate.field_trans_date_value, '%Y%m') = :year_date)) 
    AND ((nfd.status = '1') 
    AND (nfd.type IN ('line_item')) 
    AND (amount.field_amount_value IS NOT NULL)
    AND nfd.uid = :uid";

    if ($income) {
      $query .= " AND (catdata.name IS NOT NULL)) GROUP BY cat_name";
    }
    else {
      $query .= " AND (parent_term.name IS NOT NULL)) GROUP BY parent_name";
    }

    $results = $connection->query($query, [
      ':year_date' => $year_month,
      ':uid' => \Drupal::currentUser()->id(),
    ])->fetchAll();

    $categories = [];
    $numbers = [];
    $options = [];
    $options['title'] = '';

    foreach ($results as $key => $result) {
      if ($result->amount && isset($result->parent_name)) {
        if ($result->parent_name != 'Income' && !$income) {
          $options['title'] = 'Spending';
          $categories[] = $result->parent_name;
          $numbers[] = [
            'name' => $result->parent_name,
            'y' => (int) $result->amount,
          ];
        }
        else {
          if ($result->parent_name == 'Income' && $income) {
            $options['title'] = 'Income';
            $categories[] = $result->cat_name;
            $numbers[] = [
              'name' => $result->cat_name,
              'y' => (int) $result->amount,
            ];
          }
        }
      }
    }

    $cat_nums = [$categories, $numbers];

    //cache results
    \Drupal::cache()->set($cache_id, $cat_nums);

    return $cat_nums;
  }

}

