<?php

namespace Drupal\bcalc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\views\Views;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class Homepage.
 */
class Homepage extends ControllerBase {

  /**
   * Build.
   *
   * @return array
   *   Return homepage theme array.
   */
  public function home() {

    $tabs_content = $this->buildHomepageTabs();

    return [
      '#theme' => 'homepage',
      '#tabs' => $tabs_content['tabs'],
      '#tabscontent' => $tabs_content['content'],
      '#cache' => [
        'max-age' => 0,
      ]
    ];
  }
  private function buildHomepageTabs() {

    $items = [];

    $tabs_content = [];

    $active = TRUE;
    for($i=1;$i<=12;$i++) {

      $month = str_pad($i, 2, '0', STR_PAD_LEFT);
      $year = 2018;

      $year_month = $year . '-' . $month;
      $beginning_of_month = $year_month . '-01';
      $end_of_month = $year_month . '-' . cal_days_in_month(CAL_GREGORIAN, $month, $year);

      $count = \Drupal::entityQuery('node')
        ->condition('type', 'line_item')
        ->condition('field_trans_date', [
          $beginning_of_month,
          $end_of_month
        ], 'BETWEEN')
        ->condition('uid', \Drupal::currentUser()->id())
        ->sort('field_trans_date')
        ->count()
        ->execute();

      if ($count) {
        $content = views_embed_view('category_summary', 'default', $year . $month);
        $dateObj = \DateTime::createFromFormat('!m', $i);
        $monthName = $dateObj->format('F');

        //CHART
        //$chart = $this->buildChart('2018' . $month);
        //$chart2 = $this->buildChart('2018' . $month, TRUE);

        $tabs_content[] = [
          'active' => $active,
          'month_name' => strtolower($monthName),
          'content' => $content,
          'edit_arg' => '2018-' . $dateObj->format('m'),
          'chart' => '',
          'chart2' => ''
        ];

        $url = Url::fromUserInput('#' . strtolower($monthName), ['attributes' => ['data-toggle' => 'tab']]);
        $link = Link::fromTextAndUrl(t($monthName), $url)->toString();

        if ($active) {
          $items[] = [
            '#markup' => $link,
            '#wrapper_attributes' => ['class' => 'active']
          ];
        }
        else {
          $items[] = $link;
        }

        if ($active) {
          $active = FALSE;
        }
      }
    }

    $list = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => [
        'class' => 'nav nav-tabs'
      ]
    ];
    return ['tabs' => $list, 'content' => $tabs_content];
  }

  private function buildChart($year_month, $income = false) {
    $connection = Database::getConnection();

    $query = "";

    if($income) {
      $query .= "SELECT MIN(parent_term.name) AS parent_name, catdata.name AS cat_name,";
    } else {
      $query .= "SELECT parent_term.name AS parent_name, MIN(catdata.name) AS cat_name,";
    }

    $query .= " SUM(amount.field_amount_value) AS amount, MIN(nfd.nid) AS nid, MIN(catdata.tid) AS cat_tid, MIN(parent_term.tid) AS parent_tid
    FROM 
    {node_field_data} nfd
    LEFT JOIN {node__field_category} nfc ON nfd.nid = nfc.entity_id
    LEFT JOIN {taxonomy_term_field_data} catdata ON nfc.field_category_target_id = catdata.tid
    LEFT JOIN {taxonomy_term_hierarchy} h ON catdata.tid = h.tid
    LEFT JOIN {taxonomy_term_field_data} parent_term ON h.parent = parent_term.tid
    LEFT JOIN {node__field_amount} amount ON nfd.nid = amount.entity_id
    LEFT JOIN {node__field_trans_date} transdate ON nfd.nid = transdate.entity_id 
    WHERE ((DATE_FORMAT(transdate.field_trans_date_value, '%Y%m') = :year_date)) 
    AND ((nfd.status = '1') 
    AND (nfd.type IN ('line_item')) 
    AND (amount.field_amount_value IS NOT NULL)";

    if($income) {
      $query .= " AND (catdata.name IS NOT NULL)) GROUP BY cat_name";
    } else {
      $query .= " AND (parent_term.name IS NOT NULL)) GROUP BY parent_name";
    }

    $results = $connection->query($query, [':year_date' => $year_month])->fetchAll();

    $categories = [];
    $seriesData = [];
    $numbers = [];
    $options = [];
    $options['title'] = '';

    foreach ($results as $key => $result) {
      if($result->amount) {
        if($result->parent_name != 'Income' && !$income) {
          $options['title'] = 'Spending';
          $categories[] = $result->parent_name;
          $numbers[] = [
            'name' => $result->parent_name,
            'y' => (int) $result->amount
          ];
        } else if($result->parent_name == 'Income' && $income) {
          $options['title'] = 'Income';
          $categories[] = $result->cat_name;
          $numbers[] = [
            'name' => $result->cat_name,
            'y' => (int) $result->amount
          ];
        }
      }
    }
    $seriesData[] = ["data" => $numbers];

    $options['type'] = 'pie';
    $options['yaxis_title'] = t('Y-Axis');
    $options['yaxis_min'] = '';
    $options['yaxis_max'] = '';
    $options['xaxis_labels_rotation'] = 0;
    $options['xaxis_title'] = t('X-Axis');

    return  [
      '#theme' => 'charts_api_example',
      '#library' => 'highcharts',
      '#categories' => $categories,
      '#seriesData' => $seriesData,
      '#options' => $options,
    ];
  }
}

