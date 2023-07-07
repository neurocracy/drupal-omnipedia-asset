<?php

declare(strict_types=1);

namespace Drupal\omnipedia_asset\EventSubscriber\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Aggregated asset routes event subscriber.
 */
class AssetRoutesEventSubscriber extends RouteSubscriberBase {

  /**
   * Alter routes.
   *
   * This sets the '_maintenance_access' route option to true for the CSS and JS
   * system asset routes. JavaScript aggregation seems to be automatically
   * disabled in maintenance mode but CSS aggregation is not. This is a problem
   * in Drupal 10.1 because users who don't have the 'access site in maintenance
   * mode' permission will instead be served the maintenance page as text/html
   * when their browser requests the aggregated CSS route if the aggregate
   * that's being requested has not been generated yet.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   A collection of routes.
   *
   * @see \Drupal\system\Routing\AssetRoutes::routes()
   *   Defines the CSS and JS aggregated asset routes.
   *
   * @see \Drupal\Core\Site\MaintenanceMode
   *   Determines whether a user can access a route in maintenance mode or is
   *   served the maintenance page. Also checks for the '_maintenance_access'
   *   option on routes to bypass that check.
   *
   * @see https://www.drupal.org/project/drupal/issues/3373328
   *   Drupal core issue opened regarding this.
   *
   * @todo Remove JavaScript route alteration as it's not necessary?
   *
   * @todo Potential security issues with any of this?
   */
  public function alterRoutes(RouteCollection $collection) {

    foreach (['system.css_asset', 'system.js_asset'] as $routeName) {
      $collection->get($routeName)->setOption('_maintenance_access', true);
    }

  }

}
