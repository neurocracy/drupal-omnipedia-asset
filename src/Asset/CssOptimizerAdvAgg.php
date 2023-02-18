<?php

declare(strict_types=1);

namespace Drupal\omnipedia_asset\Asset;

use Drupal\advagg\Asset\AssetOptimizer;
use Drupal\advagg\Asset\CssOptimizer;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CssOptimizerAdvAgg extends CssOptimizer {

  /**
   * The Drupal site settings name to attempt to retrieve the host from.
   */
  protected const SETTINGS_NAME = 'primary_host';

  /**
   * The AdvAgg CSS optimizer service that we decorate.
   *
   * @var \Drupal\advagg\Asset\AssetOptimizer
   */
  protected AssetOptimizer $cssOptimizer;

  /**
   * The host name to rewrite URLs to.
   *
   * @var string
   *
   * @see $this->rewriteFileURI()
   */
  protected string $host;

  /**
   * Our logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $loggerChannel;

  /**
   * The scheme to rewrite URLs to.
   *
   * @var string
   *
   * @see $this->rewriteFileURI()
   */
  protected string $scheme;

  /**
   * The Drupal site settings.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected Settings $settings;

  /**
   * The Symfony request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Service constructor; saves dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Drupal configuration factory.
   *
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $eventDispatcher
   *   The Drupal container-aware event dispatcher.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The AdvAgg cache.
   *
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Symfony request stack.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The Drupal site settings.
   *
   * @param \Drupal\advagg\Asset\AssetOptimizer $cssOptimizer
   *   The AdvAgg CSS optimizer service that we decorate.
   */
  public function __construct(
    ConfigFactoryInterface        $configFactory,
    ContainerAwareEventDispatcher $eventDispatcher,
    CacheBackendInterface         $cache,
    LoggerInterface               $loggerChannel,
    RequestStack                  $requestStack,
    Settings                      $settings,
    AssetOptimizer                $cssOptimizer
  ) {

    parent::__construct($configFactory, $eventDispatcher, $cache);

    // dpm('Base path:');
    // dpm($this->basePath);

    $this->cssOptimizer   = $cssOptimizer;
    $this->loggerChannel  = $loggerChannel;
    $this->requestStack   = $requestStack;
    $this->settings       = $settings;

    // If the primary host setting is set, use that.
    if (!empty($this->settings->get(self::SETTINGS_NAME))) {

      $this->host = $this->settings->get(self::SETTINGS_NAME);

    // If not, set it to the host that Symfony says we're being requested from
    // as a fallback.
    } else {
      $this->host = $this->requestStack->getMainRequest()->getHttpHost();
    }

    $this->scheme = $this->requestStack->getMainRequest()->getScheme();

  }

  public function updateUrls($contents, $path) {
    // Determine the file's directory including the Drupal base path.
    $directory = base_path() . dirname($path) . '/';

    \preg_match_all(
      '/url\(\s*([\'"]?)(?![a-z]+:|\/+|\#|\%23+)([^\'")]+)([\'"]?)\s*\)/i',
      $contents,
      $matches
    );

    // if (!empty($matches[0])) {

    //   dpm($path);

    //   dpm($matches);

    // }

    // Alter all internal url() paths. Leave external paths alone. We don't need
    // to normalize absolute paths here because that will be done later. Also
    // ignore SVG paths (# or %23). Expected form: url("/images/file.jpg") which
    // gets converted to url("${directory}/images/file.jpg").
    return preg_replace('/url\(\s*([\'"]?)(?![a-z]+:|\/+|\#|\%23+)([^\'")]+)([\'"]?)\s*\)/i', 'url(\1' . $directory . '\2\3)', $contents);
  }

}
