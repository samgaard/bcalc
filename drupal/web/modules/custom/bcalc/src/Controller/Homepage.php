<?php

namespace Drupal\bcalc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
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

    //MAKE A TAB FOR EACH MONTH
    for ($i = 1; $i <= 12; $i++) {

      //FORMAT BEGINNING AND END OF MONTH
      $month = str_pad($i, 2, '0', STR_PAD_LEFT);
      $year = date('Y');
      $year_month = $year . '-' . $month;
      $beginning_of_month = $year_month . '-01';
      $end_of_month = $year_month . '-' . cal_days_in_month(CAL_GREGORIAN, $month, $year);

      //FIND LINE ITEMS WITHIN THIS DATE RANGE
      $ids = \Drupal::entityQuery('node')
        ->condition('type', 'line_item')
        ->condition('field_trans_date', [
          $beginning_of_month,
          $end_of_month
        ], 'BETWEEN')
        ->condition('uid', \Drupal::currentUser()->id())
        ->execute();

      if (count($ids)) {

        //LOAD ENTITIES
        $nodes = Node::loadMultiple($ids);

        $tables =
        $line_items =
        $line_items['xtra'] = [];

        $line_items['xtra']['total_out'] =
        $line_items['xtra']['total_in'] =
        $line_items['xtra']['paychecks'] =
        $line_items['xtra']['from_savings'] =
        $line_items['xtra']['from_trust'] =
        $line_items['xtra']['uncategorized'] = 0;

        //GROUP BY CATEGORY
        foreach($nodes as $node) {

          $amount = $node->get('field_amount')->value;

          //CATEGORIZED
          if ($cid = $node->get('field_category')->target_id) {

            $category_term = Term::load($cid);

            //GET TERM PARENT
            $parents = \Drupal::entityTypeManager()
              ->getStorage('taxonomy_term')
              ->loadParents($category_term->id());
            $parent_term = array_shift($parents);

            //RECORD SEPARATE INCOME DETAILS
            $income = FALSE;
            if(!empty($parent_term) && $parent_term->label() == "Income") {
              $income = TRUE;
              if($category_term->label() == "From Savings") {
                $line_items['xtra']['from_savings'] += $amount;
              }
              if($category_term->label() == "Trust") {
                $line_items['xtra']['from_trust'] += $amount;
              }
              if(in_array($category_term->label(), ['Emily Income', 'Sam Income', 'Other'])) {
                $line_items['xtra']['paychecks'] += $amount;
              }
            }

            //DEFAULT ARRAY KEYS. USE PARENT NAME AS KEY FOR SORTING
            if(!isset($line_items[$parent_term->label()])) {
              $line_items[$parent_term->label()] = ['sum' => 0];
            }
            if(!isset($line_items[$parent_term->label()][$cid])) {
              $line_items[$parent_term->label()][$cid] = ['sum' => 0];
            }
            if(!isset($line_items[$parent_term->label()][$cid]['title'])) {
              $line_items[$parent_term->label()][$cid]['title'] = $category_term->label();
            }
            if(!isset($line_items[$parent_term->label()]['title'])) {
              $line_items[$parent_term->label()]['title'] = $parent_term->label();
            }
            if(!isset($line_items[$parent_term->label()][$cid]['title'])) {
              $line_items[$parent_term->label()][$cid]['title'] = $category_term->label();
            }

            //PARENT TOTAL
            $line_items[$parent_term->label()]['sum'] += $amount;

            //CATEGORY TOTAL
            $line_items[$parent_term->label()][$cid]['sum'] += $amount;

            //MONTH TOTALS
            if($income) {
              $line_items['xtra']['total_in'] += $amount;
            } else {
              $line_items['xtra']['total_out'] += $amount;
            }
          }
          else {
            //NO CATEGORY
            $line_items['xtra']['uncategorized'] += $amount;
            //ADD TO TOTAL OUT
            //$line_items['xtra']['total_out'] += $amount;
          }
        }

        $month_total_amount = $line_items['xtra']['total_out'];

        //SORT BY PARENT NAME
        ksort($line_items);

        //BUILD TABLE OF DATA
        $content = '';
        foreach ($line_items as $parent_key => $parents) {
          $rows = [];
          if($parent_key != 'xtra') {
            $cat_title = '';
            $cat_sum = 0;
            $chart = '';
            $categories = [];
            $numbers = [];

            foreach ($parents as $cat_key => $cat_item) {
              switch ($cat_key) {
                case 'sum':
                  $cat_sum = $cat_item;
                  break;
                case 'title':
                  $cat_title = $cat_item;
                  break;
                default:
                  //TABLE ROW
                  if (is_numeric($cat_key)) {
                    $rows[] = [
                      [
                        'data' => $parents[$cat_key]['title'],
                      ],
                      [
                        'data' => $parents[$cat_key]['sum'],
                        'class' => 'text-right'
                      ]
                    ];

                    //DATA FOR CHART
                    $categories[] = $parents[$cat_key]['title'];
                    $numbers[] = [
                      'name' => $parents[$cat_key]['title'],
                      'y' => (int) $parents[$cat_key]['sum'],
                    ];
                  }
                  break;
              }
            }

            //APPEND PERCENT NEXT TO PARENT NAME
            if($cat_title != 'Income') {
              $cat_title .= ' ' . number_format((($cat_sum / $month_total_amount) * 100)) . '%';
            }

            //TOTAL ROW
            $rows[] = [
              'data' => [
                'Total',
                [
                  'data' => $cat_sum,
                  'class' => 'text-right',
                ],
              ],
              'class' => 'total-row'
            ];

            //THEME TABLE
            $table = [
              '#theme' => 'table',
              '#rows' => $rows,
              '#header' => []
            ];

            //CHART
            if (!empty($numbers)) {
              $chart = $this->categoryChart($categories, $numbers,'Series ' . $parent_key);
            }

            //ADD TO TABLES ARRAY WITH CATEGORY TITLE
            $tables[] = [
              'title' => $cat_title,
              'table' => $table,
              'chart' => $chart
            ];
          }
        }

        $dateObj = \DateTime::createFromFormat('!m', $i);
        $monthName = $dateObj->format('F');


        $build_chart = ''; //$this->buildChart('2018' . $month);

        $tabs_content[] = [
          'active' => $active,
          'month_name' => strtolower($monthName),
          'tables' => $tables,
          'uncategorized' => $line_items['xtra']['uncategorized'],
          'from_savings' => $line_items['xtra']['from_savings'],
          'from_trust' => $line_items['xtra']['from_trust'],
          'paychecks' => $line_items['xtra']['paychecks'],
          'total_in' => $line_items['xtra']['total_in'],
          'total_out' => $month_total_amount,
          'edit_arg' => '2018-' . $dateObj->format('m'),
          'chart' => $build_chart,
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
    LEFT JOIN {taxonomy_term__parent} h ON catdata.tid = h.parent_target_id
    LEFT JOIN {taxonomy_term_field_data} parent_term ON h.parent_target_id = parent_term.tid
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
      if($result->amount && isset($result->parent_name)) {
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
    $seriesData[] = [
      'name' => 'Series 2',
      'color' => '#8bbc21',
      'type' => 'pie',
      "data" => $numbers
    ];

    $options['type'] = 'pie';
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

    return  [
      '#theme' => 'charts_api_example',
      '#library' => 'highcharts',
      '#categories' => $categories,
      '#seriesData' => $seriesData,
      '#options' => $options,
      '#id' => $chartId,
    ];



    /*
     *
    // Customize options here.
    $options = [
      'type' => $this->chartSettings['type'],
      'title' => $this->t('Chart title'),
      'xaxis_title' => $this->t('X-Axis'),
      'yaxis_title' => $this->t('Y-Axis'),
      'yaxis_min' => '',
      'yaxis_max' => '',
      'three_dimensional' => FALSE,
      'title_position' => 'out',
      'legend_position' => 'right',
      'data_labels'=> $this->chartSettings['data_labels'],
      // 'grouping'   => TRUE,
      'colors'   => $this->chartSettings['colors'],
      'min'   => $this->chartSettings['min'],
      'max'   => $this->chartSettings['max'],
      'yaxis_prefix'   => $this->chartSettings['yaxis_prefix'],
      'yaxis_suffix'   => $this->chartSettings['yaxis_suffix'],
      'data_markers'   => $this->chartSettings['data_markers'],
      'red_from'   => $this->chartSettings['red_from'],
      'red_to'   => $this->chartSettings['red_to'],
      'yellow_from'   => $this->chartSettings['yellow_from'],
      'yellow_to'   => $this->chartSettings['yellow_to'],
      'green_from'   => $this->chartSettings['green_from'],
      'green_to'   => $this->chartSettings['green_to'],
    ];

    // Sample data format.
    $categories = ['Category 1', 'Category 2', 'Category 3', 'Category 4'];
    $seriesData[] = [
      'name' => 'Series 1',
      'color' => '#0d233a',
      'type' => NULL,
      'data' => [250, 350, 400, 200],
    ];
    switch ($this->chartSettings['type']) {
      default:
        $seriesData[] = [
          'name' => 'Series 2',
          'color' => '#8bbc21',
          'type' => 'column',
          'data' => [150, 450, 500, 300],
        ];
        $seriesData[] = [
          'name' => 'Series 3',
          'color' => '#910000',
          'type' => 'area',
          'data' => [0, 0, 60, 90],
        ];
      case 'pie':
      case 'donut':

    }

    // Creates a UUID for the chart ID.
    //$uuid_service = \Drupal::service('uuid');
    $chartId = 'chart-' . $this->uuidService->generate();

    $build = [
      '#theme' => 'charts_api_example',
      '#library' => (string) $library,
      '#categories' => $categories,
      '#seriesData' => $seriesData,
      '#options' => $options,
      '#id' => $chartId,
      '#override' => [],
    ];

    return $build;
     */


  }

  private function categoryChart($categories = [], $numbers = [], $series_name = '') {
    $seriesData = [];
    $options = [];
    $options['title'] = '';

    $seriesData[] = [
      'name' => $series_name,
      'color' => '#8bbc21',
      'type' => 'pie',
      "data" => $numbers,
    ];

    $options['type'] = 'pie';
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
}

