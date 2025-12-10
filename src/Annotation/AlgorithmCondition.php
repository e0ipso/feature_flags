<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an Algorithm Condition annotation object.
 *
 * Algorithm conditions evaluate context to determine whether a decision
 * algorithm should be applied. Conditions can use AND, OR, NOT operators
 * and are evaluated client-side in JavaScript.
 *
 * Plugin namespace: Plugin\AlgorithmCondition
 *
 * @see \Drupal\feature_flags\Plugin\AlgorithmCondition\AlgorithmConditionInterface
 * @see \Drupal\feature_flags\Plugin\AlgorithmCondition\AlgorithmConditionPluginBase
 * @see plugin_api
 *
 * @Annotation
 */
class AlgorithmCondition extends Plugin {

  /**
   * The plugin ID.
   */
  public readonly string $id;

  /**
   * The human-readable name of the condition.
   *
   * @ingroup plugin_translatable
   */
  public readonly string $label;

  /**
   * A description of what this condition evaluates.
   *
   * This is displayed in the admin interface when adding conditions.
   *
   * @ingroup plugin_translatable
   */
  public readonly string $description;

  /**
   * The context key this condition reads from.
   *
   * This key is used to retrieve the value from the context object
   * during client-side evaluation.
   *
   * Example: 'user_id', 'user_tier', 'subscription_status'
   */
  public readonly string $context_key;

  /**
   * The Drupal library name containing the JavaScript implementation.
   *
   * Format: 'feature_flags/condition.{plugin_id}'
   *
   * Example: 'feature_flags/condition.user_id'
   */
  public readonly string $js_library;

  /**
   * The JavaScript class name that implements this condition.
   *
   * The class must extend BaseCondition and implement the evaluate() method.
   *
   * Example: 'UserIdCondition'
   */
  public readonly string $js_class;

}
