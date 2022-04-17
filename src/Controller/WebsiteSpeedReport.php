<?php

namespace Drupal\website_speed\Controller;

use Drupal\Core\Block\BlockManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\website_speed\WebsiteSpeedChart;

/**
 * Controller for the Website Speed Reports.
 */
class WebsiteSpeedReport extends ControllerBase {

  /**
   * Manage the generation of blocks in the controller.
   *
   * @var Drupal\Core\Block\BlockManager
   */
  private $blockManager;

  /**
   * The active database connection.
   *
   * @var Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  private $container;

  /**
   * Flag to decide to show charts or not.
   *
   * @var bool
   */
  private $showCharts;

  /**
   * Construct the WebsiteSpeedReport Controller.
   *
   * @param \Drupal\core\block\BlockManager $blockManager
   *   The block manager to instantiate the report blocks.
   * @param \Drupal\Core\Database\Connection $database
   *   The active database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to check if charts module is enabled.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container interface.
   */
  public function __construct(BlockManager $blockManager, Connection $database, ModuleHandlerInterface $module_handler, ContainerInterface $container) {
    $this->blockManager = $blockManager;
    $this->database = $database;
    $this->showCharts = $module_handler->moduleExists('charts');
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('database'),
      $container->get('module_handler'),
      $container
    );
  }

  /**
   * Return render array for performance summary page.
   */
  public function showSummaryPage(Request $request) {
    $render_array['performance_summary'] = $this->showSummaryStatistics();
    if ($this->showCharts) {
      $render_array['page_speed_distribution'] = $this->showPageSpeedDistribution();
    }
    $render_array['page_speed_by_route_average_response'] = $this->showSpeedByRoute('route', 'average_response');
    $render_array['page_speed_by_url_average_response'] = $this->showSpeedByRoute('url', 'average_response');

    return $render_array;
  }

  /**
   * Return render array for performance summary page.
   */
  public function showReportsByRoute(Request $request) {
    if ($this->showCharts) {
      $render_array['page_speed_by_route_graph'] = $this->showSpeedByRoute('route', 'average_response', 'chart');
    }
    $render_array['page_speed_by_route_total_time'] = $this->showSpeedByRoute('route', 'total_time');
    $render_array['page_speed_by_route_count_requests'] = $this->showSpeedByRoute('route', 'num_requests');
    $render_array['page_speed_by_route_average_response'] = $this->showSpeedByRoute('route', 'average_response');
    $render_array['page_speed_by_route_max_response'] = $this->showSpeedByRoute('route', 'max_response');
    return $render_array;
  }

  /**
   * Return render array for performance summary page.
   */
  public function showReportsByUrl(Request $request) {
    if ($this->showCharts) {
      $render_array['page_speed_by_url_graph'] = $this->showSpeedByRoute('url', 'average_response', 'chart');
    }
    $render_array['page_speed_by_url_total_time'] = $this->showSpeedByRoute('url', 'total_time');
    $render_array['page_speed_by_url_count_requests'] = $this->showSpeedByRoute('url', 'num_requests');
    $render_array['page_speed_by_url_average_response'] = $this->showSpeedByRoute('url', 'average_response');
    $render_array['page_speed_by_url_max_response'] = $this->showSpeedByRoute('url', 'max_response');
    return $render_array;
  }

  /**
   * Return render array for page speed distribution.
   */
  public function showPageSpeedDistribution() {
    $config = $this->config('website_speed.settings');
    $bins_in_distribution = $config->get('bins_in_distribution');
    $chart = new WebsiteSpeedChart($this->container);
    $build = $chart->build;
    $options = $chart->build['#options'];
    $options['type'] = 'column';
    $options['title'] = '';
    $options['yaxis_title'] = 'Percentage';
    $options['xaxis_title'] = 'Time Range';

    $page_speed_column = 'response_start';
    if ($config->get('use_terminate_time')) {
      $page_speed_column = 'kernel_terminate';
    }
    // Get summary statistics from the data to be used
    // to define chart ranges.
    $query = "SELECT
      AVG(ws.${page_speed_column}) AS avg_response_start,
      MAX(ws.${page_speed_column}) AS max_response_start,
      MIN(ws.${page_speed_column}) AS min_response_start,
      SUM(ws.${page_speed_column}) AS total_time,
      COUNT(*) AS total_requests
      FROM website_speed_timings ws";
    $stats = $this->database->query($query)->fetchAssoc();
    // https://stats.libretexts.org/Bookshelves/Introductory_Statistics/Book%3A_Inferential_Statistics_and_Probability/07%3A_Continuous_Random_Variables/7.02%3A_Exponential_Distribution
    // Getting range till 5 x average to cover 99% of responses.
    $min = 0;
    // Round up to the nearest 5 second.
    $max = ceil(($stats['avg_response_start'] * 5) / 5) * 5;
    // Divide range into X intervals based on config.
    $num_divisions = $bins_in_distribution;
    $increment = $max / $num_divisions;
    $boundaries[0] = 0;
    $categories = [];
    $query = '';
    // Dynamically generate the query to create the binned
    // distribution for the page speeds.
    for ($i = 1; $i <= $num_divisions; $i++) {
      // Keep track of boundaries. Not used for now.
      $boundaries[$i] = round($i * $increment, 2);
      $range_start = $boundaries[$i - 1];
      $range_end = $boundaries[$i];
      if ($query != '') {
        $query .= ' UNION ALL ';
      }
      // Generate the labels for the bins to be used
      // for the x axis category labels.
      $range_name = "${range_start}s - ${range_end}s";
      $range_end_val = $range_end;
      // Last range is all the way to the max value.
      if ($i == $num_divisions) {
        $range_end_val = $max + 1;
        $range_name = "${range_start}s+";
      }
      $query .= " SELECT
        '${range_name}' AS range_name,
        COUNT(*) AS num_requests,
        SUM(ws.${page_speed_column}) AS total_time
        FROM website_speed_timings ws
        WHERE ws.${page_speed_column} BETWEEN ${range_start} AND ${range_end_val} ";
    }
    $result = $this->database->query($query);
    $data = [];
    $categories = [];
    // Loop through and find the data for the graph.
    while ($row = $result->fetchAssoc()) {
      $categories[] = $row['range_name'];
      $data1[] = round($row['num_requests'] * 100 / $stats['total_requests'], 2);
      $data2[] = round($row['total_time'] * 100 / $stats['total_time']);
    }

    $seriesData[] = [
      'name' => 'Percentage of Requests',
      'color' => '#0678BE',
      'type' => 'column',
      'data' => $data1,
    ];
    $seriesData[] = [
      'name' => 'Percentage of Time',
      'color' => '#53B0EB',
      'type' => 'column',
      'data' => $data2,
    ];
    $build['#categories'] = $categories;
    $build['#seriesData'] = $seriesData;
    $build['#options'] = $options;
    $chart_build = $build;
    $build = [];
    $build['chart_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => "Page Speed Distribution",
    ];
    if ($chart->canRenderChart()) {
      $build['chart_description'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => "The chart plots the percentage of requests falling
        within a page speed range along with the percentage of total time
        consumed by all the requests within that range. This should give
        an idea of the impact of the slow pages and a sense of where you
        should focus on for performance optimization on the site.",
      ];
      $build['chart'] = $chart_build;
    }
    else {
      $build['chart_description'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => "You will have to set the configuration for the charts module to render charts.",
      ];
    }
    return $build;
  }

  /**
   * Return render array for page speed distribution.
   *
   * @param string $group_by
   *   The column to group by for the report.
   *   url - Group by the URL
   *   route - Group by the route.
   * @param string $type
   *   Type of ordering for the table. Supported types are
   *   total_time - Order by total time spent
   *   count_requests - Order by total number of requests
   *   average_response - Average Response time.
   *   max_response - Max Response time.
   * @param string $return
   *   Option to pick between table or chart returns.
   */
  public function showSpeedByRoute($group_by, $type, $return = 'table') {
    $config = $this->config('website_speed.settings');
    $rows_per_table = $config->get('items_per_table');
    $page_speed_column = 'response_start';
    if ($config->get('use_terminate_time')) {
      $page_speed_column = 'kernel_terminate';
    }
    $title_context = ['@num_rows' => $rows_per_table];

    switch ($group_by) {
      case 'route':
        $group_by = 'route_name';
        $main_column_name = 'route_name';
        $main_column_title = 'Route';
        $title_context['@name'] = 'Routes';
        break;

      case 'url':
        $group_by = 'url';
        $main_column_name = 'url';
        $main_column_title = 'URL';
        $title_context['@name'] = 'URLs';
        break;

    }
    switch ($type) {
      case 'total_time':
        $order_by = 'total_time DESC';
        $table_title = $this->t('Top @num_rows @name by Total Time Spent', $title_context);
        break;

      case 'num_requests':
        $order_by = 'num_requests DESC';
        $table_title = $this->t('Top @num_rows @name by Total Number of Requests', $title_context);
        break;

      case 'average_response':
        $order_by = 'avg_response_start DESC';
        $table_title = $this->t('Top @num_rows @name by Average Response Time', $title_context);
        break;

      case 'max_response':
        $order_by = 'max_response_start DESC';
        $table_title = $this->t('Top @num_rows @name by Maximum Response Time', $title_context);
        break;

    }

    $stats = $this->getStats();

    // Get the average page speed.
    $query = "SELECT
        ${main_column_name},
        AVG(ws.${page_speed_column}) AS avg_response_start,
        MAX(ws.${page_speed_column}) AS max_response_start,
        MIN(ws.${page_speed_column}) AS min_response_start,
        SUM(ws.${page_speed_column}) AS total_time,
        COUNT(*) AS num_requests
      FROM website_speed_timings ws
      GROUP BY ${group_by}
      ORDER BY ${order_by}
      LIMIT ${rows_per_table}";
    $result = $this->database->query($query);
    $build = [];
    $rows = [];
    $data1 = [];
    $data2 = [];
    $data3 = [];
    while ($row = $result->fetchAssoc()) {
      $categories[] = $row[$main_column_name];
      $data1[] = round($row['num_requests'] * 100 / $stats['total_requests'], 2);
      $data2[] = round($row['total_time'] * 100 / $stats['total_time']);
      $data3[] = round($row['avg_response_start'], 2);
      foreach ($row as $key => $value) {
        if ($key == 'route_name' || $key == 'url') {
          $row[$key] = $value;
        }
        elseif ($key == 'num_requests') {
          $row[$key] = $this->formatNumber($value, 'count');
        }
        else {
          $row[$key] = $this->formatNumber($value, 'sec');
        }
      }
      $rows[] = $row;
    }
    if ($return == 'table') {
      $build['summary_title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $table_title,
      ];
      $build['summary_table'] = [
        '#type' => "table",
        '#header' => [
          $main_column_title,
          "Avg. Page. Time",
          "Max. Page. Time",
          "Min. Page. Time",
          "Total Time",
          "Total Requests",
        ],
        '#sticky' => TRUE,
        '#rows' => $rows,
      ];
      $build['separator'] = [
        '#type' => 'html_tag',
        '#tag' => 'br',
      ];
    }
    elseif ($return == 'chart') {
      $chart = new WebsiteSpeedChart($this->container);
      $build = $chart->build;
      $options = $chart->build['#options'];
      $options['type'] = 'bar';
      $options['xaxis_min'] = -50;
      unset($options['title']);
      $options['yaxis_title'] = 'Requests';
      $options['xaxis_title'] = 'Percentage';
      $seriesData[] = [
        'name' => 'Percentage of Requests',
        'color' => '#0678BE',
        'type' => 'bar',
        'data' => $data1,
      ];
      $seriesData[] = [
        'name' => 'Percentage of Time',
        'color' => '#53B0EB',
        'type' => 'bar',
        'data' => $data2,
      ];
      $seriesData[] = [
        'name' => 'Response Time',
        'color' => '#FF3333',
        'type' => 'line',
        'data' => $data3,
      ];
      $build['#categories'] = $categories;
      $build['#seriesData'] = $seriesData;
      $build['#options'] = $options;
      $chart_build = $build;
      $build = [];
      $build['chart_title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $table_title,
      ];
      if ($chart->canRenderChart()) {
        $build['chart_description'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => "The chart plots the percentage of requests for a
            ${main_column_title} along with the percentage of total time
            consumed by all the requests for the same. This should give
            an idea of the impact of the slow pages and a sense of where you
            should focus on for performance optimization on the site.",
        ];
        $build['chart'] = $chart_build;
      }
      else {
        $build['chart_description'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => "You will have to set the configuration for the charts module to render charts.",
        ];
      }
    }
    return $build;
  }

  /**
   * Return render array for page speed distribution.
   */
  public function showSummaryStatistics() {
    $page_speed_column = 'response_start';
    if ($this->config('website_speed.settings')->get('use_terminate_time')) {
      $page_speed_column = 'kernel_terminate';
    }
    // Get the average page speed.
    $query = "SELECT
        AVG(ws.response_start) AS avg_response_start,
        AVG(ws.kernel_terminate) AS avg_kernel_terminate,
        MAX(ws.response_start) AS max_response_start,
        MAX(ws.kernel_terminate) AS max_kernel_terminate,
        MIN(ws.response_start) AS min_response_start,
        MIN(ws.kernel_terminate) AS min_kernel_terminate,
        SUM(ws.${page_speed_column}) AS total_time,
        COUNT(*) AS count_items
      FROM website_speed_timings ws";
    $result = $this->database->query($query)->fetch();
    $build = [];
    $rows = [];
    if ($result) {
      $rows[] = [
        $this->t('Average time to page response'),
        $this->formatNumber($result->avg_response_start, 'sec'),
      ];
      $rows[] = [
        $this->t('Maximum time to page response'),
        $this->formatNumber($result->max_response_start, 'sec'),
      ];
      $rows[] = [
        $this->t('Minimum time to page response'),
        $this->formatNumber($result->min_response_start, 'sec'),
      ];
      $rows[] = [
        $this->t('Average time to end of PHP execution'),
        $this->formatNumber($result->avg_kernel_terminate, 'sec'),
      ];
      $rows[] = [
        $this->t('Maximum time to end of PHP execution'),
        $this->formatNumber($result->max_kernel_terminate, 'sec'),
      ];
      $rows[] = [
        $this->t('Minimum time to end of PHP execution'),
        $this->formatNumber($result->min_kernel_terminate, 'sec'),
      ];
      $rows[] = [
        $this->t('Total time spent across all requests'),
        $this->formatNumber($result->total_time, 'sec'),
      ];
      $rows[] = [
        $this->t('Number of Requests'),
        $this->formatNumber($result->count_items, 'count'),
      ];
    }

    $build['summary_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Summary Statistics'),
    ];
    $build['summary_table'] = [
      '#type' => "table",
      '#header' => ["Statistic", "Value"],
      '#rows' => $rows,
    ];
    $build['separator'] = [
      '#type' => 'html_tag',
      '#tag' => 'br',
    ];
    return $build;
  }

  /**
   * Return formatted number for given presentation type.
   *
   * @param float $input
   *   Input value.
   * @param string $type
   *   Supported types - sec, count.
   *
   * @return string
   *   Returns the formatted number
   */
  public function formatNumber($input, $type) {
    if ($type == 'sec') {
      return number_format($input, 2) . 's';
    }
    if ($type == 'count') {
      return number_format($input, 0);
    }
  }

  /**
   * Return statistics from the page speed timings table.
   *
   * @return array
   *   Returns statistics from the table.
   */
  public function getStats() {
    $config = $this->config('website_speed.settings');
    $page_speed_column = 'response_start';
    if ($config->get('use_terminate_time')) {
      $page_speed_column = 'kernel_terminate';
    }
    // Get summary statistics from the data to be used
    // to define chart ranges.
    $query = "SELECT
      AVG(ws.${page_speed_column}) AS avg_response_start,
      MAX(ws.${page_speed_column}) AS max_response_start,
      MIN(ws.${page_speed_column}) AS min_response_start,
      SUM(ws.${page_speed_column}) AS total_time,
      COUNT(*) AS total_requests
      FROM website_speed_timings ws";
    $stats = $this->database->query($query)->fetchAssoc();
    return $stats;
  }

}
