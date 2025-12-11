<?php

declare(strict_types=1);

namespace Drupal\feature_flags;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\feature_flags\Entity\FeatureFlag;

/**
 * Provides a listing of Feature Flag entities.
 */
class FeatureFlagListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['machine_name'] = $this->t('Machine name');
    $header['status'] = $this->t('Status');
    $header['variants_count'] = $this->t('Variants');
    $header['algorithms_count'] = $this->t('Algorithms');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof FeatureFlag);

    $row['label'] = $entity->toLink($entity->label(), 'edit-form');
    $row['machine_name'] = $entity->id();

    // Status badge.
    if ($entity->status()) {
      $row['status'] = [
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Enabled'),
          '#attributes' => [
            'class' => ['badge', 'badge-success'],
            'style' => 'background-color: #28a745; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.875em;',
          ],
        ],
      ];
    }
    else {
      $row['status'] = [
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Disabled'),
          '#attributes' => [
            'class' => ['badge', 'badge-secondary'],
            'style' => 'background-color: #6c757d; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.875em;',
          ],
        ],
      ];
    }

    $row['variants_count'] = count($entity->getVariants());
    $row['algorithms_count'] = count($entity->getAlgorithms());

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);

    // Ensure edit and delete operations are present.
    if ($entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'weight' => 10,
        'url' => $entity->toUrl('edit-form'),
      ];
    }

    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 20,
        'url' => $entity->toUrl('delete-form'),
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();

    // Attach admin list styling library.
    $build['#attached']['library'][] = 'feature_flags/admin_list';

    // Add empty state message if no entities.
    if (empty($build['table']['#rows'])) {
      $build['empty'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('No feature flags have been created yet.') . '</p>',
        '#prefix' => '<div class="empty-state">',
        '#suffix' => '</div>',
      ];
    }

    return $build;
  }

}
