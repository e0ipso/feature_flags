<?php

declare(strict_types=1);

namespace Drupal\feature_flags;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\feature_flags\Entity\FeatureFlag;
use Psr\Log\LoggerInterface;

/**
 * Builds feature flag attachments for pages.
 *
 * Separates the logic of building drupalSettings and library attachments
 * from the hook implementation for better testability and maintainability.
 */
final class FeatureFlagAttachmentBuilder {

  /**
   * The logger channel for feature flags.
   */
  private readonly LoggerInterface $logger;

  /**
   * Constructs a FeatureFlagAttachmentBuilder.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly DecisionAlgorithmPluginManager $algorithmPluginManager,
    private readonly AlgorithmConditionPluginManager $conditionPluginManager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->logger = $logger_factory->get('feature_flags');
  }

  /**
   * Builds page attachments for enabled feature flags.
   *
   * @param array $attachments
   *   The attachments array to modify.
   */
  public function buildAttachments(array &$attachments): void {
    $flags = $this->loadEnabledFlags();

    // Early return if no enabled flags.
    if (empty($flags)) {
      return;
    }

    $config = $this->configFactory->get('feature_flags.settings');
    $settings = $this->buildSettings($config, $flags);

    // Attach drupalSettings.
    $attachments['#attached']['drupalSettings']['featureFlags'] = $settings['drupalSettings'];

    // Attach base libraries.
    $attachments['#attached']['library'][] = 'feature_flags/base';
    $attachments['#attached']['library'][] = 'feature_flags/feature_flags';

    // Attach plugin-specific libraries.
    foreach ($settings['libraries'] as $library) {
      $attachments['#attached']['library'][] = $library;
    }
  }

  /**
   * Loads all enabled feature flags.
   *
   * @return array
   *   Array of enabled feature flag entities.
   */
  private function loadEnabledFlags(): array {
    $storage = $this->entityTypeManager->getStorage('feature_flag');
    return $storage->loadByProperties(['status' => TRUE]);
  }

  /**
   * Builds settings array for drupalSettings and library tracking.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   * @param array $flags
   *   Array of feature flag entities.
   *
   * @return array
   *   Array with 'drupalSettings' and 'libraries' keys.
   */
  private function buildSettings($config, array $flags): array {
    $drupal_settings = [
      'settings' => [
        'debug' => $config->get('debug_mode') ?? FALSE,
        'persist' => $config->get('persist_decisions') ?? FALSE,
      ],
      'flags' => [],
      'libraries' => [
        'algorithms' => [],
        'conditions' => [],
      ],
    ];

    $libraries = [];

    foreach ($flags as $flag) {
      $flag_data = $this->buildFlagData($flag, $libraries);
      $drupal_settings['flags'][$flag->id()] = $flag_data;
    }

    return [
      'drupalSettings' => $drupal_settings,
      'libraries' => array_unique($libraries),
    ];
  }

  /**
   * Builds data array for a single feature flag.
   *
   * @param \Drupal\feature_flags\Entity\FeatureFlag $flag
   *   The feature flag entity.
   * @param array $libraries
   *   Array to populate with required libraries.
   *
   * @return array
   *   Flag data for drupalSettings.
   */
  private function buildFlagData(FeatureFlag $flag, array &$libraries): array {
    $flag_data = [
      'id' => $flag->id(),
      'label' => $flag->label(),
      'variants' => $flag->get('variants') ?? [],
      'algorithms' => [],
    ];

    $algorithms = $flag->get('algorithms') ?? [];
    foreach ($algorithms as $algorithm_data) {
      $algorithm_info = $this->buildAlgorithmData($algorithm_data, $libraries);

      if ($algorithm_info !== NULL) {
        $flag_data['algorithms'][] = $algorithm_info;
      }
    }

    return $flag_data;
  }

  /**
   * Builds data array for a single algorithm.
   *
   * Instantiates the plugin to get JavaScript settings and tracks required
   * libraries for attachment.
   *
   * @param array $algorithm_data
   *   Algorithm configuration from entity.
   * @param array $libraries
   *   Array to populate with required libraries.
   *
   * @return array|null
   *   Algorithm data for drupalSettings, or NULL if plugin instantiation fails.
   */
  private function buildAlgorithmData(array $algorithm_data, array &$libraries): ?array {
    $plugin_id = $algorithm_data['plugin_id'] ?? NULL;

    // Skip if plugin ID is missing from configuration.
    if ($plugin_id === NULL) {
      return NULL;
    }

    try {
      $plugin = $this->algorithmPluginManager->createInstance(
        $plugin_id,
        $algorithm_data['configuration'] ?? []
      );
      $definition = $this->algorithmPluginManager->getDefinition($plugin_id);

      $algorithm_info = [
        'uuid' => $algorithm_data['uuid'] ?? NULL,
        'pluginId' => $plugin_id,
        'jsClass' => $definition['js_class'] ?? NULL,
        'configuration' => $plugin->getJavaScriptSettings(),
        'conditions' => [],
      ];

      // Track library for this algorithm.
      if (!empty($definition['js_library'])) {
        $libraries[] = $definition['js_library'];
      }

      // Process conditions for this algorithm.
      $conditions = $algorithm_data['conditions'] ?? [];
      foreach ($conditions as $condition_data) {
        $condition_info = $this->buildConditionData($condition_data, $libraries);

        if ($condition_info !== NULL) {
          $algorithm_info['conditions'][] = $condition_info;
        }
      }

      return $algorithm_info;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to instantiate algorithm plugin @plugin_id: @message', [
        '@plugin_id' => $plugin_id,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Builds data array for a single condition.
   *
   * Instantiates the condition plugin to get JavaScript settings and tracks
   * required libraries for attachment.
   *
   * @param array $condition_data
   *   Condition configuration from algorithm.
   * @param array $libraries
   *   Array to populate with required libraries.
   *
   * @return array|null
   *   Condition data for drupalSettings, or NULL if plugin instantiation fails.
   */
  private function buildConditionData(array $condition_data, array &$libraries): ?array {
    $condition_plugin_id = $condition_data['plugin_id'] ?? NULL;

    // Skip if plugin ID is missing from configuration.
    if ($condition_plugin_id === NULL) {
      return NULL;
    }

    try {
      $condition_plugin = $this->conditionPluginManager->createInstance(
        $condition_plugin_id,
        $condition_data['configuration'] ?? []
      );
      $condition_definition = $this->conditionPluginManager->getDefinition($condition_plugin_id);

      // Track library for this condition.
      if (!empty($condition_definition['js_library'])) {
        $libraries[] = $condition_definition['js_library'];
      }

      return [
        'uuid' => $condition_data['uuid'] ?? NULL,
        'pluginId' => $condition_plugin_id,
        'jsClass' => $condition_definition['js_class'] ?? NULL,
        'operator' => $condition_data['operator'] ?? 'OR',
        'configuration' => $condition_plugin->getJavaScriptSettings(),
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to instantiate condition plugin @plugin_id: @message', [
        '@plugin_id' => $condition_plugin_id,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
