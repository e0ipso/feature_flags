<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Plugin\DecisionAlgorithm;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for Decision Algorithm plugins.
 *
 * Decision algorithms determine which variant a user receives. Each plugin
 * provides both configuration form elements (PHP) and client-side logic (JS).
 */
interface DecisionAlgorithmInterface extends PluginInspectionInterface, ConfigurableInterface, PluginFormInterface {

  /**
   * Gets the JavaScript settings for this algorithm.
   *
   * These settings are passed to the JavaScript implementation of the
   * algorithm and should contain all necessary configuration for client-side
   * decision making.
   *
   * @return array
   *   An associative array of settings to pass to JavaScript.
   */
  public function getJavaScriptSettings(): array;

  /**
   * Gets the label of the algorithm.
   *
   * @return string
   *   The algorithm label.
   */
  public function getLabel(): string;

  /**
   * Gets the description of the algorithm.
   *
   * @return string
   *   The algorithm description.
   */
  public function getDescription(): string;

}
