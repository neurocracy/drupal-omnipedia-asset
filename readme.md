This contains the source files for the "*Omnipedia - Asset*" Drupal module,
which provides CSS and JavaScript asset-related functionality for
[Omnipedia](https://omnipedia.app/).

⚠️ ***[Why open source? / Spoiler warning](https://omnipedia.app/open-source)***

----

# Requirements

* [Drupal 10.1](https://www.drupal.org/download)

* PHP 8.1

* [Composer](https://getcomposer.org/)

----

# Installation


### Set up

Ensure that you have your Drupal installation set up with the correct Composer
installer types such as those provided by [the `drupal/recommended-project`
template](https://www.drupal.org/docs/develop/using-composer/starting-a-site-using-drupal-composer-project-templates#s-drupalrecommended-project).
If you're starting from scratch, simply requiring that template and following
[the Drupal.org Composer
documentation](https://www.drupal.org/docs/develop/using-composer/starting-a-site-using-drupal-composer-project-templates)
should get you up and running.

### Repository

In your root `composer.json`, add the following to the `"repositories"` section:

```json
"drupal/omnipedia_asset": {
  "type": "vcs",
  "url": "https://github.com/neurocracy/drupal-omnipedia-asset.git"
}
```

### Installing

Once you've completed all of the above, run `composer require
"drupal/omnipedia_asset:^2.0@dev"` in the root of your project to have
Composer install this and its required dependencies for you.

----

# Major breaking changes

The following major version bumps indicate breaking changes:

* 2.x:

  * Now requires Drupal core 10.1 due to significant changes to its asset aggregation.

  * Moved AdvAgg event subscriber to new [`omnipedia_asset_advagg` module](modules/omnipedia_asset_advagg) so the main module does not need to require [`drupal/advagg`](https://www.drupal.org/project/advagg); this new module now requires `drupal/advagg:^6.0.0`.

  * Renamed classes and services to remove redundant/unnecessary "core" and "advagg":

    * Class `\Drupal\omnipedia_asset\Asset\CssOptimizerCore` to `\Drupal\omnipedia_asset\Asset\CssOptimizer`

    * Service `omnipedia_asset.css.optimizer.core` to `omnipedia_asset.css.optimizer`

    * Class `\Drupal\omnipedia_asset_advagg\EventSubscriber\Asset\AdvAggCssOptimizationEventSubscriber` to `\Drupal\omnipedia_asset_advagg\EventSubscriber\Asset\CssOptimizationEventSubscriber`

    * Service `omnipedia_asset_advagg.advagg_css_optimization_event_subscriber` to `omnipedia_asset_advagg.css_optimization_event_subscriber`.
