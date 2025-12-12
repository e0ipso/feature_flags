<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Decision Algorithm attribute object.
 *
 * Decision algorithms determine which variant a user receives based on
 * context and configuration. Each algorithm plugin has both a PHP
 * implementation (for admin configuration) and a JavaScript implementation
 * (for client-side resolution).
 *
 * Plugin namespace: Plugin\DecisionAlgorithm
 *
 * @see \Drupal\feature_flags\Plugin\DecisionAlgorithm\DecisionAlgorithmInterface
 * @see \Drupal\feature_flags\Plugin\DecisionAlgorithm\DecisionAlgorithmPluginBase
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class DecisionAlgorithm extends Plugin {

  /**
   * Constructs a DecisionAlgorithm attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable name of the decision algorithm.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   A description of the decision algorithm displayed in admin interface.
   * @param string $js_library
   *   The Drupal library name containing JavaScript implementation.
   *   Format: 'feature_flags/algorithm.{plugin_id}'.
   * @param string $js_class
   *   The JavaScript class name that implements this algorithm.
   *   Must extend BaseAlgorithm and implement decide() method.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly string $js_library = '',
    public readonly string $js_class = '',
    public readonly ?string $deriver = NULL,
  ) {}

}
