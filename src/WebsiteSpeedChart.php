<?php

namespace Drupal\website_speed;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Wrapper class to handle dependency with Charts module.
 */
class WebsiteSpeedChart {

  use StringTranslationTrait;

  /**
   * The build array for the chart.
   *
   * @var array
   */
  public $build;

  /**
   * The default chart settings from the charts module.
   *
   * @var \Drupal\charts\Services\ChartsSettingsServiceInterface
   */
  public $chartSettings;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * Construct a chart build object and load default settings.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Service container.
   */
  public function __construct(ContainerInterface $container) {
    $this->chartSettings = $container->get('charts.settings')->getChartsSettings();
    $this->uuidService = $container->get('uuid');

    $library = $this->chartSettings['library'];

    // Set default options.
    $options = [
      'type'                => $this->chartSettings['type'],
      'title'               => $this->t('Chart title'),
      'xaxis_title'         => $this->t('X-Axis'),
      'yaxis_title'         => $this->t('Y-Axis'),
      'yaxis_min'           => '',
      'yaxis_max'           => '',
      'three_dimensional'   => FALSE,
      'title_position'      => 'out',
      'legend_position'     => 'right',
      'data_labels'         => $this->chartSettings['data_labels'],
      'tooltips'            => $this->chartSettings['tooltips'],
      'grouping'            => FALSE,
      'colors'              => $this->chartSettings['colors'],
      'min'                 => $this->chartSettings['min'],
      'max'                 => $this->chartSettings['max'],
      'yaxis_prefix'        => $this->chartSettings['yaxis_prefix'],
      'yaxis_suffix'        => $this->chartSettings['yaxis_suffix'],
      'data_markers'        => $this->chartSettings['data_markers'],
      'red_from'            => $this->chartSettings['red_from'],
      'red_to'              => $this->chartSettings['red_to'],
      'yellow_from'         => $this->chartSettings['yellow_from'],
      'yellow_to'           => $this->chartSettings['yellow_to'],
      'green_from'          => $this->chartSettings['green_from'],
      'green_to'            => $this->chartSettings['green_to'],
    ];

    // Creates a UUID for the chart ID.
    $chartId = 'chart-' . $this->uuidService->generate();

    $this->build = [
      '#theme' => 'website_speed_chart',
      '#library' => (string) $library,
      '#categories' => [],
      '#seriesData' => [],
      '#options' => $options,
      '#id' => $chartId,
      '#override' => [],
    ];

  }

  /**
   * Check if the chart can be shown based on config settings.
   */
  public function canRenderChart() {
    if (!isset($this->chartSettings['library'])) {
      return FALSE;
    }
    return TRUE;
  }

}
