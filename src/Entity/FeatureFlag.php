<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Feature Flag entity.
 *
 * @ConfigEntityType(
 *   id = "feature_flag",
 *   label = @Translation("Feature Flag"),
 *   label_collection = @Translation("Feature Flags"),
 *   label_singular = @Translation("feature flag"),
 *   label_plural = @Translation("feature flags"),
 *   handlers = {
 *     "list_builder" = "Drupal\feature_flags\FeatureFlagListBuilder",
 *     "form" = {
 *       "add" = "Drupal\feature_flags\Form\FeatureFlagForm",
 *       "edit" = "Drupal\feature_flags\Form\FeatureFlagForm",
 *       "delete" = "Drupal\feature_flags\Form\FeatureFlagDeleteForm",
 *     },
 *   },
 *   config_prefix = "feature_flag",
 *   admin_permission = "administer feature flags",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "status",
 *     "variants",
 *     "algorithms",
 *   },
 *   links = {
 *     "add-form" = "/admin/config/services/feature-flags/add",
 *     "edit-form" = "/admin/config/services/feature-flags/{feature_flag}/edit",
 *     "delete-form" = "/admin/config/services/feature-flags/{feature_flag}/delete",
 *     "collection" = "/admin/config/services/feature-flags/list",
 *   },
 * )
 */
class FeatureFlag extends ConfigEntityBase {

  /**
   * The feature flag ID (machine name).
   */
  protected string $id;

  /**
   * The feature flag label.
   */
  protected string $label;

  /**
   * The feature flag description.
   */
  protected string $description = '';

  /**
   * The variants for this feature flag.
   *
   * Each variant has:
   * - uuid: Unique identifier (auto-generated)
   * - label: Human-readable label
   * - value: JSON-encoded value.
   */
  protected array $variants = [];

  /**
   * The decision algorithms for this feature flag.
   *
   * Each algorithm has:
   * - uuid: Unique identifier (auto-generated)
   * - plugin_id: The plugin ID
   * - configuration: Plugin-specific configuration
   * - conditions: Array of conditions
   * - weight: Order weight.
   */
  protected array $algorithms = [];

  /**
   * Gets the description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Sets the description.
   */
  public function setDescription(string $description): static {
    $this->description = $description;
    return $this;
  }

  /**
   * Gets all variants.
   */
  public function getVariants(): array {
    return $this->variants;
  }

  /**
   * Sets all variants.
   */
  public function setVariants(array $variants): static {
    // Ensure all variants have UUIDs.
    foreach ($variants as &$variant) {
      if (empty($variant['uuid'])) {
        $variant['uuid'] = \Drupal::service('uuid')->generate();
      }
    }
    $this->variants = $variants;
    return $this;
  }

  /**
   * Adds a variant.
   */
  public function addVariant(string $label, string $value): static {
    $this->variants[] = [
      'uuid' => \Drupal::service('uuid')->generate(),
      'label' => $label,
      'value' => $value,
    ];
    return $this;
  }

  /**
   * Removes a variant by UUID.
   */
  public function removeVariant(string $uuid): static {
    $this->variants = array_filter(
      $this->variants,
      fn($variant) => $variant['uuid'] !== $uuid
    );
    // Re-index array.
    $this->variants = array_values($this->variants);
    return $this;
  }

  /**
   * Gets a variant by UUID.
   */
  public function getVariant(string $uuid): ?array {
    foreach ($this->variants as $variant) {
      if ($variant['uuid'] === $uuid) {
        return $variant;
      }
    }
    return NULL;
  }

  /**
   * Gets all algorithms.
   */
  public function getAlgorithms(): array {
    return $this->algorithms;
  }

  /**
   * Sets all algorithms.
   */
  public function setAlgorithms(array $algorithms): static {
    // Ensure all algorithms and conditions have UUIDs.
    foreach ($algorithms as &$algorithm) {
      if (empty($algorithm['uuid'])) {
        $algorithm['uuid'] = \Drupal::service('uuid')->generate();
      }
      if (!isset($algorithm['weight'])) {
        $algorithm['weight'] = 0;
      }
      if (!empty($algorithm['conditions'])) {
        foreach ($algorithm['conditions'] as &$condition) {
          if (empty($condition['uuid'])) {
            $condition['uuid'] = \Drupal::service('uuid')->generate();
          }
        }
      }
    }
    $this->algorithms = $algorithms;
    return $this;
  }

  /**
   * Adds an algorithm.
   */
  public function addAlgorithm(string $plugin_id, array $configuration = [], array $conditions = [], int $weight = 0): static {
    $algorithm = [
      'uuid' => \Drupal::service('uuid')->generate(),
      'plugin_id' => $plugin_id,
      'configuration' => $configuration,
      'conditions' => $conditions,
      'weight' => $weight,
    ];

    // Ensure conditions have UUIDs.
    foreach ($algorithm['conditions'] as &$condition) {
      if (empty($condition['uuid'])) {
        $condition['uuid'] = \Drupal::service('uuid')->generate();
      }
    }

    $this->algorithms[] = $algorithm;
    return $this;
  }

  /**
   * Removes an algorithm by UUID.
   */
  public function removeAlgorithm(string $uuid): static {
    $this->algorithms = array_filter(
      $this->algorithms,
      fn($algorithm) => $algorithm['uuid'] !== $uuid
    );
    // Re-index array.
    $this->algorithms = array_values($this->algorithms);
    return $this;
  }

  /**
   * Gets an algorithm by UUID.
   */
  public function getAlgorithm(string $uuid): ?array {
    foreach ($this->algorithms as $algorithm) {
      if ($algorithm['uuid'] === $uuid) {
        return $algorithm;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    // Ensure all variants have UUIDs.
    foreach ($this->variants as &$variant) {
      if (empty($variant['uuid'])) {
        $variant['uuid'] = \Drupal::service('uuid')->generate();
      }
    }

    // Ensure all algorithms and conditions have UUIDs.
    foreach ($this->algorithms as &$algorithm) {
      if (empty($algorithm['uuid'])) {
        $algorithm['uuid'] = \Drupal::service('uuid')->generate();
      }
      if (!empty($algorithm['conditions'])) {
        foreach ($algorithm['conditions'] as &$condition) {
          if (empty($condition['uuid'])) {
            $condition['uuid'] = \Drupal::service('uuid')->generate();
          }
        }
      }
    }

    // Sort algorithms by weight.
    usort($this->algorithms, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    // Call parent to calculate dependencies and return $this for chaining.
    parent::calculateDependencies();

    // Add dependencies for algorithm plugins.
    foreach ($this->algorithms as $algorithm) {
      if (!empty($algorithm['plugin_id'])) {
        // Algorithm plugins don't create module dependencies in this simple
        // case, but if they were provided by other modules, we'd add them here.
      }
    }

    return $this;
  }

}
