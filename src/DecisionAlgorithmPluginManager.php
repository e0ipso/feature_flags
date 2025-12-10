<?php

declare(strict_types=1);

namespace Drupal\feature_flags;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\feature_flags\Annotation\DecisionAlgorithm as DecisionAlgorithmAnnotation;
use Drupal\feature_flags\Plugin\DecisionAlgorithm\DecisionAlgorithmInterface;

/**
 * DecisionAlgorithm plugin manager.
 */
final class DecisionAlgorithmPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/DecisionAlgorithm',
      $namespaces,
      $module_handler,
      DecisionAlgorithmInterface::class,
      DecisionAlgorithmAnnotation::class,
    );
    $this->alterInfo('decision_algorithm_info');
    $this->setCacheBackend($cache_backend, 'decision_algorithm_plugins');
  }

}
