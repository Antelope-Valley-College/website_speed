<?php

namespace Drupal\website_speed\Controller;

use Drupal\Core\Block\BlockManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Connection;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * {@inheritdoc}
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
   * Construct the WebsiteSpeedReport Controller.
   *
   * @param \Drupal\core\block\BlockManager $blockManager
   *   The block manager to instantiate the report blocks.
   * @param \Drupal\Core\Database\Connection $database
   *   The active database connection.
   */
  public function __construct(BlockManager $blockManager, Connection $database) {
    $this->blockManager = $blockManager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('database')
    );
  }

  /**
   * Return render array for performance summary page.
   */
  public function showSummaryPage(Request $request) {
    $render_array['performance_summary'] = $this->showSummaryStatistics();
    $render_array['page_speed_by_route_average_response'] = $this->showSpeedByRoute('route', 'average_response');
    $render_array['page_speed_by_url_average_response'] = $this->showSpeedByRoute('url', 'average_response');
    $render_array['page_speed_distribution'] = $this->showPageSpeedDistribution();
    return $render_array;
  }

  /**
   * Return render array for performance summary page.
   */
  public function showReportsByRoute(Request $request) {
    $render_array['page_speed_by_route_total_time'] = $this->showSpeedByRoute('route', 'total_time');
    $render_array['page_speed_by_route_count_requests'] = $this->showSpeedByRoute('route', 'count_requests');
    $render_array['page_speed_by_route_average_response'] = $this->showSpeedByRoute('route', 'average_response');
    $render_array['page_speed_by_route_max_response'] = $this->showSpeedByRoute('route', 'max_response');
    return $render_array;
  }

  /**
   * Return render array for performance summary page.
   */
  public function showReportsByUrl(Request $request) {
    $render_array['page_speed_by_url_total_time'] = $this->showSpeedByRoute('url', 'total_time');
    $render_array['page_speed_by_url_count_requests'] = $this->showSpeedByRoute('url', 'count_requests');
    $render_array['page_speed_by_url_average_response'] = $this->showSpeedByRoute('url', 'average_response');
    $render_array['page_speed_by_url_max_response'] = $this->showSpeedByRoute('url', 'max_response');
    return $render_array;
  }

  /**
   * Return render array for page speed distribution.
   */
  public function showPageSpeedDistribution() {
    $build = [];
    $build['summary_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $this->t('Page Speed Distribution'),
    ];
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
   */
  public function showSpeedByRoute($group_by, $type) {
    switch ($group_by) {
      case 'route':
        $group_by = 'route_name';
        $main_column_name = 'route_name';
        $main_column_title = 'Route';
        $title_context = ['@name' => 'Routes'];
        break;

      case 'url':
        $group_by = 'url';
        $main_column_name = 'url';
        $main_column_title = 'URL';
        $title_context = ['@name' => 'URLs'];
        break;

    }
    switch ($type) {
      case 'total_time':
        $order_by = 'total_time DESC';
        $table_title = $this->t('Top 10 @name by Total Time Spent', $title_context);
        break;

      case 'count_requests':
        $order_by = 'count_items DESC';
        $table_title = $this->t('Top 10 @name by Total Number of Requests', $title_context);
        break;

      case 'average_response':
        $order_by = 'avg_response_start DESC';
        $table_title = $this->t('Top 10 @name by Average Response Time', $title_context);
        break;

      case 'max_response':
        $order_by = 'max_response_start DESC';
        $table_title = $this->t('Top 10 @name by Maximum Response Time', $title_context);
        break;

    }

    // Get the average page speed.
    $query = "SELECT
        ${main_column_name},
        AVG(ws.response_start) AS avg_response_start,
        MAX(ws.response_start) AS max_response_start,
        MIN(ws.response_start) AS min_response_start,
        AVG(ws.kernel_terminate) AS avg_kernel_terminate,
        MAX(ws.kernel_terminate) AS max_kernel_terminate,
        MIN(ws.kernel_terminate) AS min_kernel_terminate,
        SUM(ws.kernel_terminate) AS total_time,
        COUNT(*) AS count_items
      FROM website_speed_timings ws
      GROUP BY ${group_by}
      ORDER BY ${order_by}
      LIMIT 10";
    $result = $this->database->query($query);
    $build = [];
    $rows = [];
    while ($row = $result->fetchAssoc()) {
      foreach ($row as $key => $value) {
        if ($key == 'route_name' || $key == 'url') {
          $row[$key] = $value;
        }
        elseif ($key == 'count_items') {
          $row[$key] = $this->formatNumber($value, 'count');
        }
        else {
          $row[$key] = $this->formatNumber($value, 'sec');
        }
      }
      $rows[] = $row;
    }

    $build['summary_title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => $table_title,
    ];
    $build['summary_table'] = [
      '#type' => "table",
      '#header' => [
        $main_column_title,
        "Avg. Resp. Time",
        "Max. Resp. Time",
        "Min. Resp. Time",
        "Avg. Exec. Time",
        "Max. Exec. Time",
        "Min. Exec. Time",
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
    return $build;
  }

  /**
   * Return render array for page speed distribution.
   */
  public function showSummaryStatistics() {
    // Get the average page speed.
    $query = 'SELECT
        AVG(ws.response_start) AS avg_response_start,
        AVG(ws.kernel_terminate) AS avg_kernel_terminate,
        MAX(ws.response_start) AS max_response_start,
        MAX(ws.kernel_terminate) AS max_kernel_terminate,
        MIN(ws.response_start) AS min_response_start,
        MIN(ws.kernel_terminate) AS min_kernel_terminate,
        COUNT(*) AS count_items
      FROM website_speed_timings ws';
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
   * @param $input
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

}
