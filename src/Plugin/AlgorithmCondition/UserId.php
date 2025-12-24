<?php

declare(strict_types=1);

namespace Drupal\feature_flags\Plugin\AlgorithmCondition;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feature_flags\Attribute\AlgorithmCondition;

/**
 * User ID condition plugin.
 *
 * Matches against specific user IDs provided in the context.
 */
#[AlgorithmCondition(
  id: 'user_id',
  label: new TranslatableMarkup('User ID'),
  description: new TranslatableMarkup('Match against specific user IDs'),
  context_key: 'user_id',
  js_library: 'feature_flags/condition.user_id',
)]
class UserId extends AlgorithmConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $values = $this->configuration['values'] ?? [];
    $values_string = is_array($values) ? implode(', ', $values) : '';

    $form['values'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User IDs'),
      '#description' => $this->t('Enter user IDs separated by commas (e.g., "1, 5, 10")'),
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
        $this->t('Please enter at least one user ID.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $values_string = $form_state->getValue('values', '');

    // Split by comma and trim whitespace to handle "1, 2, 3" or "1,2,3".
    $values = array_map('trim', explode(',', $values_string));

    // Remove empty values to handle trailing commas gracefully.
    $values = array_filter($values, fn($v) => $v !== '');

    // Reset array keys to ensure sequential numeric indices.
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
