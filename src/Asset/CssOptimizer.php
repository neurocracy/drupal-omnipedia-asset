<?php

declare(strict_types=1);

namespace Drupal\omnipedia_asset\Asset;

use Drupal\Core\Asset\AssetOptimizerInterface;
use Drupal\Core\Asset\CssOptimizer as CssOptimizerCore;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Site\Settings;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorated Drupal core CSS asset optimizer that enforces primary host.
 */
class CssOptimizer extends CssOptimizerCore {

  /**
   * The Drupal site settings name to attempt to retrieve the host from.
   */
  protected const SETTINGS_NAME = 'primary_host';

  /**
   * The CSS optimizer service that we decorate.
   *
   * @var \Drupal\Core\Asset\AssetOptimizerInterface
   */
  protected AssetOptimizerInterface $cssOptimizer;

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
   * @param \Drupal\Core\File\FileUrlGeneratorInterface|null $fileUrlGenerator
   *   The Drupal file URL generator.
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
   * @param \Drupal\Core\Asset\AssetOptimizerInterface $cssOptimizer
   *   The CSS optimizer service that we decorate.
   */
  public function __construct(
    FileUrlGeneratorInterface $fileUrlGenerator,
    LoggerInterface           $loggerChannel,
    RequestStack              $requestStack,
    Settings                  $settings,
    AssetOptimizerInterface   $cssOptimizer
  ) {

    parent::__construct($fileUrlGenerator);

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

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Asset\CssOptimizer::processFile()
   *   Exact copy of this method without any changes so that
   *   \preg_replace_callback() operates on the current instance and not the
   *   core instance we decorate.
   *
   * @todo Can this be removed so we don't have to copy core?
   */
  protected function processFile($css_asset) {
    $contents = $this->loadFile($css_asset['data'], TRUE);

    $contents = $this->clean($contents);

    // Get the parent directory of this file, relative to the Drupal root.
    $css_base_path = substr($css_asset['data'], 0, strrpos($css_asset['data'], '/'));
    // Store base path.
    $this->rewriteFileURIBasePath = $css_base_path . '/';

    // Anchor all paths in the CSS with its base URL, ignoring external and absolute paths.
    return preg_replace_callback('/url\(\s*[\'"]?(?![a-z]+:|\/+)([^\'")]+)[\'"]?\s*\)/i', [$this, 'rewriteFileURI'], $contents);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Asset\CssOptimizer::rewriteFileURI()
   *   Copied from this and altered to enforce our primary host for relative
   *   paths.
   */
  public function rewriteFileURI($matches) {

    // Prefix with base and remove '../' segments where possible.
    $path = $this->rewriteFileURIBasePath . $matches[1];

    $last = '';

    while ($path != $last) {

      $last = $path;

      $path = \preg_replace('`(^|/)(?!\.\./)([^/]+)/\.\./`', '$1', $path);

    }

    $url = $this->scheme . '://' . $this->host . '/' . $path;

    $this->loggerChannel->debug(
      'Core: Rewrote CSS asset <code>%match</code> into path <code>%path</code> and into URL <code>%url</code>.', [
        '%match'  => $matches[1],
        '%path'   => $path,
        '%url'    => $url,
      ]
    );

    return 'url(' . $url . ')';

  }

}
