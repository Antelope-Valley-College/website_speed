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
    // NOTE: 'charts.settings' is a config object name, not a service, so
    // $container->get('charts.settings') was never valid and always threw a
    // ServiceNotFoundException. Load it via config factory instead.
    $this->chartSettings = \Drupal::config('charts.settings')->getRawData();
    $this->uuidService = $container->get('uuid');

    // As of Charts 5.x, charts.settings nests everything under
    // 'charts_default_settings' (with 'display'/'xaxis'/'yaxis' sub-keys)
    // instead of storing keys flat at the top level. Pull out the relevant
    // sub-arrays here so the rest of this method can stay unchanged.
    $defaults = $this->chartSettings['charts_default_settings'] ?? [];	
	
    $display = $defaults['display'] ?? [];
    $gauge = $display['gauge'] ?? [];
    $xaxis = $defaults['xaxis'] ?? [];
    $yaxis = $defaults['yaxis'] ?? [];

    $library = $defaults['library'] ?? '';

    // Set default options.
    $options = [
      'type'                => $defaults['type'] ?? 'line',
      'title'               => $display['title'] ?? '',
      'xaxis_title'         => $xaxis['title'] ?? '',
      'yaxis_title'         => $yaxis['title'] ?? '',
      'yaxis_min'           => $yaxis['min'] ?? 0,
      'yaxis_max'           => $yaxis['max'] ?? 0,
      'three_dimensional'   => FALSE,
      'title_position'      => 'out',
      'legend_position'     => 'right',
      'data_labels'         => $display['data_labels'] ?? FALSE,
      'tooltips'            => $display['tooltips'] ?? TRUE,
      'grouping'            => FALSE,
      'colors'              => $display['colors'] ?? [],
      'min'                 => $yaxis['min'] ?? '',
      'max'                 => $yaxis['max'] ?? '',
      'yaxis_prefix'        => $yaxis['prefix'] ?? '',
      'yaxis_suffix'        => $yaxis['suffix'] ?? '',
      'data_markers'        => $display['data_markers'] ?? FALSE,
      'red_from'            => $gauge['red_from'] ?? '',
      'red_to'              => $gauge['red_to'] ?? '',
      'yellow_from'         => $gauge['yellow_from'] ?? '',
      'yellow_to'           => $gauge['yellow_to'] ?? '',
      'green_from'          => $gauge['green_from'] ?? '',
      'green_to'            => $gauge['green_to'] ?? '',
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
    if (empty($this->chartSettings['charts_default_settings']['library'])) {
      return FALSE;
    }
    return TRUE;
  }

}
