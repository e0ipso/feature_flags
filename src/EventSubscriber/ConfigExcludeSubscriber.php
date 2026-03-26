<?php

declare(strict_types=1);

namespace Drupal\feature_flags\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\StorageTransformEvent;
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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Config\StorageInterface $activeStorage
   *   The active storage.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected StorageInterface $activeStorage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::STORAGE_TRANSFORM_EXPORT][] = ['onConfigExport'];
    $events[ConfigEvents::STORAGE_TRANSFORM_IMPORT][] = ['onConfigImport'];
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

  /**
   * Avoids deleting existing feature flags when the setting is enabled.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   */
  public function onConfigImport(StorageTransformEvent $event): void {
    // Check if exclusion is enabled.
    $config = $this->configFactory->get('feature_flags.settings');
    $exclude = $config->get('exclude_from_config_export') ?? FALSE;

    if (!$exclude) {
      // Exclusion disabled, don't modify import.
      return;
    }

    // Get the storage being exported.
    $transformation_storage = $event->getStorage();

    // Get all feature flag config names.
    $prefix = 'feature_flags.feature_flag.';
    $config_names = $this->activeStorage->listAll($prefix);

    // Put back feature flags into the import to cancel deletion diff.
    array_map(
      fn(string $config_name) => $transformation_storage->write(
        $config_name,
        $this->activeStorage->read($config_name),
      ),
      $config_names,
    );
  }

}
