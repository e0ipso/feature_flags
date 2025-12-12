<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an Algorithm Condition attribute object.
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
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AlgorithmCondition extends Plugin {

  /**
   * Constructs an AlgorithmCondition attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The human-readable name of the condition.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   A description of what this condition evaluates.
   * @param string $context_key
   *   The context key this condition reads from.
   *   Example: 'user_id', 'user_tier', 'subscription_status'
   * @param string $js_library
   *   The Drupal library name containing JavaScript implementation.
   *   Format: 'feature_flags/condition.{plugin_id}'
   * @param string $js_class
   *   The JavaScript class name that implements this condition.
   *   Must extend BaseCondition and implement evaluate() method.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly string $context_key = '',
    public readonly string $js_library = '',
    public readonly string $js_class = '',
    public readonly ?string $deriver = NULL,
  ) {}

}
