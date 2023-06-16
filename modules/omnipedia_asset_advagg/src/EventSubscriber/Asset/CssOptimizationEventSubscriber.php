<?php

declare(strict_types=1);

namespace Drupal\omnipedia_asset_advagg\EventSubscriber\Asset;

use Drupal\advagg\Asset\AssetOptimizationEvent;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Event subscriber to replace server address in optimized CSS with host name.
 */
class CssOptimizationEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The Drupal site settings name to attempt to retrieve the host from.
   */
  protected const SETTINGS_NAME = 'primary_host';

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
   * Event subscriber constructor; saves dependencies.
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
  ) {

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

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AssetOptimizationEvent::CSS => 'onCssOptimizeServerIp',
      AssetOptimizationEvent::CSS => 'onCssOptimizeAnyIp',
    ];
  }

  /**
   * Rewrite URLs containing the current server IP address within optimized CSS.
   *
   * @param \Drupal\advagg\Asset\AssetOptimizationEvent $event
   *   The AdvAgg asset optimization event object.
   */
  public function onCssOptimizeServerIp(AssetOptimizationEvent $event): void {

    /** @var string The CSS file content. */
    $content = $event->getContent();

    /** @var string Regular expression pattern to match CSS url()s containing the server address. */
    $pattern = '/url\(\s*([\'\"]?)https?:\/\/' .
      \preg_quote($this->serverAddress) .
    '\/([^\'\")]+)([\'\"]?)\s*\)/i';

    $replacedContent = \preg_replace(
      $pattern, 'url($1' .
        $this->scheme . '://' . $this->host .
      '/$2$3)', $content,
      -1, $replaceCount
    );

    if ($replaceCount === 0) {
      return;
    }

    $event->setContent($replacedContent);

    /** @var \Drupal\Core\StringTranslation\PluralTranslatableMarkup */
    $message = $this->formatPlural(
      $replaceCount,
      'AdvAgg: Replaced 1 instance of the server address (<code>@address</code>) with the server host name (<code>@host</code>) in asset <code>@asset</code>.',
      'AdvAgg: Replaced @count instances of the server address (<code>@address</code>) with the server host name (<code>@host</code>) in asset <code>@asset</code>.',
      [
        '@address'  => $this->serverAddress,
        '@host'     => $this->host,
        '@asset'    => $event->getAsset()['data'],
      ]
    );

    $this->loggerChannel->debug($message->render());

  }

  /**
   * Rewrite URLs containing IP address within optimized CSS.
   *
   * @param \Drupal\advagg\Asset\AssetOptimizationEvent $event
   *   The AdvAgg asset optimization event object.
   */
  public function onCssOptimizeAnyIp(AssetOptimizationEvent $event): void {

    /** @var string The CSS file content. */
    $content = $event->getContent();

    /** @var string Regular expression pattern to match CSS url()s containing any IPv4 address. */
    $pattern = '/url\(\s*(?<open_quote>[\'\"]?)https?:\/\/(?<address>\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(?<path>[^\'\")]+)(?<close_quote>[\'\"]?)\s*\)/i';

    /** @var string[] */
    $foundAddresses = [];

    $replacedContent = \preg_replace_callback($pattern, function(
      array $matches
    ) use ($content, &$foundAddresses) {

      if (!\in_array($matches['address'], $foundAddresses)) {
        $foundAddresses[] = $matches['address'];
      }

      return 'url(' .
        $matches['open_quote'] . $this->scheme . '://' . $this->host . '/' .
        $matches['path'] . $matches['close_quote'] .
      ')';

    }, $content, -1, $replaceCount);

    if ($replaceCount === 0) {
      return;
    }

    $event->setContent($replacedContent);

    /** @var \Drupal\Core\StringTranslation\PluralTranslatableMarkup */
    $message = $this->formatPlural(
      count($foundAddresses),
      'AdvAgg: Replaced 1 instance of an IP address (<code>@addresses</code>) with the server host name (<code>@host</code>) in asset <code>@asset</code>.',
      'AdvAgg: Replaced @count instances of IP addresses (<code>@addresses</code>) with the server host name (<code>@host</code>) in asset <code>@asset</code>.',
      [
        '@addresses'  => implode(', ', $foundAddresses),
        '@host'       => $this->host,
        '@asset'      => $event->getAsset()['data'],
      ]
    );

    $this->loggerChannel->notice($message->render());

  }

}
