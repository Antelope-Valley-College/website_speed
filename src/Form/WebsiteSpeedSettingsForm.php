<?php

namespace Drupal\website_speed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings for for website speed module.
 */
class WebsiteSpeedSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'website_speed.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'website_speed_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('website_speed.settings');
    $form['enable_tracking'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable page speed tracking'),
      '#description' => $this->t('Use this to turn on/off tracking of page generation times.'),
      '#default_value' => $config->get('enable_tracking'),
    ];
    $form['perc_tracked'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Percentage Tracked'),
      '#maxlength' => 10,
      '#size' => 10,
      '#description' => $this->t('The percentage of requests to be tracked. Set value between 0 - 100. A precision of 3 decimal points will be used.'),
      '#default_value' => $config->get('perc_tracked'),
    ];
    $form['use_terminate_time'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use time till Terminate event'),
      '#description' => $this->t('Check this to use time till terminate event to calculate average page speed.'),
      '#default_value' => $config->get('use_terminate_time'),
    ];
    $form['items_per_table'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Rows per report table'),
      '#maxlength' => 10,
      '#size' => 10,
      '#description' => $this->t('Number of rows to be shown in each of the Top X report tables. Set value between 1 - 100'),
      '#default_value' => $config->get('items_per_table'),
    ];
    $form['bins_in_distribution'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bins in distribution'),
      '#maxlength' => 10,
      '#size' => 10,
      '#description' => $this->t('Number of bins in the page speed distribution chart. Set value between 2 - 25'),
      '#default_value' => $config->get('bins_in_distribution'),
    ];
    $form['timings_to_retain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Timing entries to retain'),
      '#maxlength' => 10,
      '#size' => 10,
      '#description' => $this->t('Number of timing entries to retain. Rest will be cleared in cron.'),
      '#default_value' => $config->get('timings_to_retain'),
    ];
    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug Mode'),
      '#default_value' => $config->get('debug_mode'),
      '#description' => $this->t('Check this to enable debug mode with more debug logging etc.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (!is_numeric($values['perc_tracked'])
        || $values['perc_tracked'] < 0
        || $values['perc_tracked'] > 100) {
      $form_state->setErrorByName('perc_tracked', $this->t('Percentage Tracked must be a value between 0 and 100.'));
    }
    if (!is_numeric($values['items_per_table'])
        || $values['items_per_table'] < 1
        || $values['items_per_table'] > 100) {
      $form_state->setErrorByName('items_per_table', $this->t('Rows per report table must be an integer between 1 and 100.'));
    }
    if (!is_numeric($values['bins_in_distribution'])
        || $values['bins_in_distribution'] < 2
        || $values['bins_in_distribution'] > 25) {
      $form_state->setErrorByName('bins_in_distribution', $this->t('Bins in Distribution must be an integer between 2 and 25.'));
    }
    if (!is_numeric($values['timings_to_retain'])
        || $values['timings_to_retain'] < 0
        || $values['timings_to_retain'] > 1000000) {
      $form_state->setErrorByName('timings_to_retain', $this->t('Timings to retain must be an integer <= 1,000,000.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $settings = $this->config('website_speed.settings');
    $settings->set(
      'enable_tracking', $form_state->getValue('enable_tracking')
    )->set(
      'perc_tracked', round($form_state->getValue('perc_tracked'), 3)
    )->set(
      'use_terminate_time', $form_state->getValue('use_terminate_time')
    )->set(
      'items_per_table', floor($form_state->getValue('items_per_table'))
    )->set(
      'bins_in_distribution', floor($form_state->getValue('bins_in_distribution'))
    )->set(
      'timings_to_retain', floor($form_state->getValue('timings_to_retain'))
    )->set(
      'debug_mode', floor($form_state->getValue('debug_mode'))
    );
    $settings->save();
  }

}
