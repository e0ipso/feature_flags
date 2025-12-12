<?php

declare(strict_types=1);

namespace Drupal\feature_flags\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to exclude feature flags from config export.
 *
 * When the "exclude_from_config_export" setting is enabled, this subscriber
 * removes all feature_flag config entities from the configuration export.
 */
final class ConfigExcludeSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a ConfigExcludeSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigManagerInterface $configManager
   *   The config manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::STORAGE_TRANSFORM_EXPORT][] = ['onConfigExport'];
    return $events;
  }

  /**
   * Excludes feature flag config entities from export when setting enabled.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onConfigExport(StorageTransformEvent $event): void {
    // Check if exclusion is enabled.
    $config = $this->configFactory->get('feature_flags.settings');
    $exclude = $config->get('exclude_from_config_export') ?? FALSE;

    if (!$exclude) {
      // Exclusion disabled, don't modify export.
      return;
    }

    // Get the storage being exported.
    $storage = $event->getStorage();

    // Get all feature flag config names.
    $prefix = 'feature_flags.feature_flag.';
    $config_names = $storage->listAll($prefix);

    // Remove each feature flag config from the export.
    foreach ($config_names as $config_name) {
      $storage->delete($config_name);
    }
  }

}
