<?php

namespace Drupal\bcalc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\views\Views;
use Drupal\Core\Render\Markup;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class Homepage.
 */
class Homepage extends ControllerBase {

  /**
   * Build.
   *
   * @return string
   *   Return Hello string.
   */
  public function home() {

    $tabs_content = buildHomepageTabs();

    return [
      '#theme' => 'homepage',
      '#tabs' => $tabs_content['tabs'],
      '#tabscontent' => $tabs_content['content'],
      '#cache' => [
        'max-age' => 0,
      ]
    ];
  }

}

function buildHomepageTabs() {


  $items = [];

  $tabs_content = [];

  $active = TRUE;
  for($i=1;$i<=12;$i++) {

    $month = str_pad($i, 2, '0', STR_PAD_LEFT);

    $view = Views::getView('line_items');
    $view->setDisplay('block_1');
    $view->setArguments(array('2017' . $month));
    $viewRendered = $view->render();
    if($viewRendered['#rows']) {
      \Drupal::service('renderer')->render($viewRendered);

      $dateObj = \DateTime::createFromFormat('!m', $i);
      $monthName = $dateObj->format('F');

      //CHART
      $chart_view = Views::getView('line_items');
      $chart_view->setDisplay('block_2');
      $chart_view->setArguments(array('2017' . $month));
      $chart_viewRendered = $chart_view->render();
      if($chart_viewRendered['#rows']) {
        \Drupal::service('renderer')->render($chart_viewRendered);
        $chart = $chart_viewRendered['#markup'];
      } else {
        $chart = '';
      }

      $tabs_content[] = [
        'active' => $active,
        'month_name' => strtolower($monthName),
        'content' => $viewRendered['#markup'],
        'edit_arg' => '2017' . $dateObj->format('m'),
        'chart' => $chart
      ];
      
      $url = Url::fromUserInput('#' . strtolower($monthName), ['attributes' => ['data-toggle' => 'tab']]);
      $link = Link::fromTextAndUrl(t($monthName), $url)->toString();

      if ($active) {
        $items[] = ['#markup' => $link, '#wrapper_attributes' => ['class' => 'active']];
      } else {
        $items[] = $link;
      }

      if($active) {
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