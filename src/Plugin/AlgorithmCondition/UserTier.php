<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Plugin\AlgorithmCondition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feature_flags\Attribute\AlgorithmCondition;

/**
 * User Tier condition plugin.
 *
 * Matches against user tier values provided via page context.
 */
#[AlgorithmCondition(
  id: 'user_tier',
  label: new TranslatableMarkup('User Tier'),
  description: new TranslatableMarkup('Match against user tier values provided via page context'),
  context_key: 'user_tier',
  js_library: 'feature_flags/condition.user_tier',
  js_class: 'UserTierCondition',
)]
class UserTier extends AlgorithmConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $values = $this->configuration['values'] ?? [];
    $values_string = is_array($values) ? implode(', ', $values) : '';

    $form['values'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Tiers'),
      '#description' => $this->t('Enter tier values separated by commas (e.g., "free, premium, enterprise"). Matching is case-sensitive.'),
      '#default_value' => $values_string,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);

    $values_string = $form_state->getValue('values', '');

    if (empty(trim($values_string))) {
      $form_state->setError(
        $form['values'],
        $this->t('Please enter at least one tier value.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $values_string = $form_state->getValue('values', '');

    // Split by comma and trim whitespace from each value.
    $values = array_map('trim', explode(',', $values_string));

    // Filter out empty values.
    $values = array_filter($values, fn($v) => $v !== '');

    $this->configuration['values'] = array_values($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getJavaScriptSettings(): array {
    return [
      'values' => $this->configuration['values'],
    ];
  }

}
