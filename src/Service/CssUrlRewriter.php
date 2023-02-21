<?php

declare(strict_types=1);

namespace Drupal\omnipedia_asset\Service;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * CSS url() rewriter service.
 *
 * @todo Add support for IPv6 addresses?
 */
class CssUrlRewriter {

  use StringTranslationTrait;

  /**
   * The Drupal site settings name to attempt to retrieve the host from.
   */
  protected const SETTINGS_NAME = 'primary_host';

  /**
   * Regular expression pattern to match CSS url() containing IPv4 addresses.
   */
  protected const PATTERN = 'url\(\s*(?<open_quote>[\'\"]?)https?:\/\/(?<address>\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(?<path>[^\'\")]+)(?<close_quote>[\'\"]?)\s*\)';

  /**
   * The host name to rewrite URLs to.
   *
   * @var string
   */
  protected string $host;

  /**
   * Our logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $loggerChannel;

  /**
   * The minimum severity to log.
   *
   * This determines what types of messages are logged by the class using this
   * trait. For example, the default value will only log notice severity or
   * higher, so info and debug messages will not be logged.
   *
   * @var bool
   *
   * @see \Drupal\Core\Logger\RfcLogLevel
   *   Defines the logging level constants.
   */
  protected bool $logSeverity = RfcLogLevel::NOTICE;

  /**
   * The scheme to rewrite URLs to.
   *
   * @var string
   */
  protected string $scheme;

  /**
   * The current server's IPv4 address.
   *
   * @var string
   */
  protected string $serverAddress;

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
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Symfony request stack.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The Drupal site settings.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The Drupal string translation service.
   */
  public function __construct(
    LoggerInterface       $loggerChannel,
    RequestStack          $requestStack,
    Settings              $settings,
    TranslationInterface  $stringTranslation
  ): void {

    $this->loggerChannel      = $loggerChannel;
    $this->requestStack       = $requestStack;
    $this->settings           = $settings;
    $this->stringTranslation  = $stringTranslation;

    /** @var \Symfony\Component\HttpFoundation\Request The main request. */
    $request = $this->requestStack->getMainRequest();

    // If the primary host setting is set, use that.
    if (!empty($this->settings->get(self::SETTINGS_NAME))) {

      $this->host = $this->settings->get(self::SETTINGS_NAME);

    // If not, set it to the host that Symfony says we're being requested from
    // as a fallback.
    } else {
      $this->host = $request->getHttpHost();
    }

    $this->scheme = $request->getScheme();

    $this->serverAddress = $request->server->get('SERVER_ADDR');

  }

  protected function log(
    int $severity, int $minimumSeverity, string $message, array $context = []
  ): void {

    if ($severity < $minimumSeverity) {
      return;
    }

    $this->loggerChannel->log($severity, $message, $context);

  }

  /**
   * Rewrite url() IPv4 addresses in the provided CSS to our primary host name.
   *
   * @param string $content
   *   The full CSS content for a given asset, usually containing selectors,
   *   properties, etc.
   *
   * @param array $asset
   *   The asset library array.
   *
   * @param string $logFor
   *   Machine name of the Drupal extension this is being invoked by, or 'core'
   *   if via a decorated Drupal core asset optimizer.
   *
   * @param bool $replaceAll
   *   If true, will replace all IPv4 addresses found with the host name. If
   *   false, will only replace the server's address if found. Defaults to
   *   false.
   *
   * @param int $logSeverity
   *   The minimum severity level to log. Levels of a lower severity level than
   *   this value will not be logged.
   *
   * @return string
   *   The $content parameter with IPv4 addresses replaced.
   */
  public function rewrite(
    string $content, array $asset, string $logFor,
    bool $replaceAll = false, int $logSeverity = RfcLogLevel::NOTICE
  ): string {

    $replacedContent = \preg_replace_callback(
      '/' . self::PATTERN . '/i',
      function(array $matches) use ($content, $asset, $logFor, $logSeverity) {
        return $this->replace(
          $matches, $content, $asset, $logFor, $logSeverity
        );
      },
      $content, -1, $replaceCount
    );

  }

  protected function replace(
    array $matches, string $content, array $asset,
    string $logFor, int $logSeverity
  ): string {}

}
