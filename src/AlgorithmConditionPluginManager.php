<?php

declare(strict_types=1);

namespace Drupal\feature_flags;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\feature_flags\Attribute\AlgorithmCondition as AlgorithmConditionAttribute;
use Drupal\feature_flags\Plugin\AlgorithmCondition\AlgorithmConditionInterface;

/**
 * AlgorithmCondition plugin manager.
 */
final class AlgorithmConditionPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/AlgorithmCondition',
      $namespaces,
      $module_handler,
      AlgorithmConditionInterface::class,
      AlgorithmConditionAttribute::class,
    );
    $this->alterInfo('algorithm_condition_info');
    $this->setCacheBackend($cache_backend, 'algorithm_condition_plugins');
  }

}
