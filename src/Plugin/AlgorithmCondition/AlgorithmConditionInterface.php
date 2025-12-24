<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Plugin\AlgorithmCondition;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for Algorithm Condition plugins.
 *
 * Conditions evaluate context to determine whether an algorithm should apply.
 * Each plugin provides configuration forms (PHP) and evaluation logic (JS).
 */
interface AlgorithmConditionInterface extends PluginInspectionInterface, ConfigurableInterface, PluginFormInterface {

  /**
   * Gets the JavaScript settings for this condition.
   *
   * These settings are passed to the JavaScript implementation and should
   * contain all necessary configuration for client-side evaluation.
   *
   * @return array
   *   An associative array of settings to pass to JavaScript.
   */
  public function getJavaScriptSettings(): array;

  /**
   * Gets the context key this condition reads from.
   *
   * @return string
   *   The context key (e.g., 'user_id').
   */
  public function getContextKey(): string;

  /**
   * Gets the label of the condition.
   *
   * @return string
   *   The condition label.
   */
  public function getLabel(): string;

  /**
   * Gets the description of the condition.
   *
   * @return string
   *   The condition description.
   */
  public function getDescription(): string;

}
