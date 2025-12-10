<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Decision Algorithm annotation object.
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
 *
 * @Annotation
 */
class DecisionAlgorithm extends Plugin {

  /**
   * The plugin ID.
   */
  public readonly string $id;

  /**
   * The human-readable name of the decision algorithm.
   *
   * @ingroup plugin_translatable
   */
  public readonly string $label;

  /**
   * A description of the decision algorithm.
   *
   * This is displayed in the admin interface when selecting algorithms.
   *
   * @ingroup plugin_translatable
   */
  public readonly string $description;

  /**
   * The Drupal library name containing the JavaScript implementation.
   *
   * Format: 'feature_flags/algorithm.{plugin_id}'
   *
   * Example: 'feature_flags/algorithm.percentage_rollout'
   */
  public readonly string $js_library;

  /**
   * The JavaScript class name that implements this algorithm.
   *
   * The class must extend BaseAlgorithm and implement the decide() method.
   *
   * Example: 'PercentageRollout'
   */
  public readonly string $js_class;

}
