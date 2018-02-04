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
      '#tabscontent' => $tabs_content['content']
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

      $tabs_content[] = [
        'active' => $active,
        'month_name' => strtolower($monthName),
        'content' => $viewRendered['#markup']
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