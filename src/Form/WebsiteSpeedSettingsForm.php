<?php

namespace Drupal\website_speed\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
      '#description' => $this->t('Number of rows to be shown in each of the Top X report tables'),
      '#default_value' => $config->get('items_per_table'),
    ];
    return parent::buildForm($form, $form_state);
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
      'use_terminate_time', $form_state->getValue('use_terminate_time')
    )->set(
      'items_per_table', $form_state->getValue('items_per_table')
    );
    $settings->save();
  }

}
