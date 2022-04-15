<?php

namespace Drupal\website_speed\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatch;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class WebsiteSpeedSubscriber.
 *
 * Provides a WebsiteSpeedSubscriber. Listens to the page events
 * to time the page.
 */
class WebsiteSpeedSubscriber implements EventSubscriberInterface {

  /**
   * The page timer.
   *
   * @var array
   */
  private $timer;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * The config for the module.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * The logger object.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  private $loggerFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * The response code that is sent for the response.
   *
   * @var int
   */
  private $responseCode;

  /**
   * Flag indicating if tracking is enabled.
   *
   * @var bool
   */
  private $disabled;

  /**
   * Flag indicating if the record has been saved.
   *
   * @var bool
   */
  private $saved;

  /**
   * Flag indicating debug mode.
   *
   * @var bool
   */
  private $debug;

  /**
   * Constructs a new WebsiteSpeedSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The logger channel factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   A request stack symfony instance.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Data of the user.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              Connection $database,
                              LoggerChannelFactoryInterface $logger_factory,
                              RequestStack $request_stack,
                              AccountInterface $current_user) {
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
    $this->timer = [];
    $this->responseCode = 0;
    $this->disabled = FALSE;
    $this->saved = FALSE;
    $this->debug = FALSE;
    // If a global timer has been initialized in index.php use that
    // instead of the local timer started at the request event.
    // There could be a small delay from the point index.php
    // starts to the point the REQUEST event is fired.
    global $_website_speed_timer;
    if (isset($_website_speed_timer)) {
      $this->timer['start'] = $_website_speed_timer;
    }
    else {
      $this->timer['start'] = microtime(TRUE);
    }
  }

  /**
   * Start page execution timer.
   *
   * @param Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The Event to process.
   */
  public function websiteSpeedOnBoot(RequestEvent $event) {
    // If the configuration to enable tracking is disabled
    // then disable the recording of speeds.
    if (!$this->configFactory->get('website_speed.settings')->get('enable_tracking')) {
      $this->disabled = TRUE;
      return;
    }
    if ($this->configFactory->get('website_speed.settings')->get('debug_mode')) {
      $this->debug = TRUE;
      return;
    }
    // The timer is initialized in the constructor but without this boot
    // subscriber, the class will not be instantiated until response is
    // ready. So even though this function does not do anything, the timer
    // still gets instantiated when this subscriber is called.
  }

  /**
   * Track time till response starts.
   *
   * @param Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The Event to process.
   */
  public function websiteSpeedOnResponseStart(ResponseEvent $event) {
    if ($this->disabled) {
      return;
    }
    // This event is raised multiple times. Record only the first.
    if (!isset($this->timer['response'])) {
      $this->timer['response'] = microtime(TRUE);
      $this->debugLog('Resp');
    }
  }

  /**
   * Track time till page termination and record.
   *
   * @param Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The Event to process.
   */
  public function websiteSpeedOnTerminate(TerminateEvent $event) {
    if ($this->disabled) {
      return;
    }
    $this->timer['terminate'] = microtime(TRUE);
    $response = $event->getResponse();
    $this->responseCode = $response->getStatusCode();
    // Recording happens on terminate event but only once
    // on the first terminate event.
    if (!$this->saved) {
      $this->saveTimerData();
    }
    $this->debugLog('Term');
  }

  /**
   * Save timer data into the database.
   */
  public function saveTimerData() {
    // If there is no Request or RouteMatch, return.
    $request = $this->requestStack->getCurrentRequest();
    $route = RouteMatch::createFromRequest($this->requestStack->getCurrentRequest())->getRouteName();
    // When there is no request or route (drush commands)
    // or when there is no response event - cache clear does not
    // seem to have response events triggered - then don't
    // track as we will have bad data points which will mess
    // up ave, max, min etc.
    if (!$request || !$route || !isset($this->timer['response'])) {
      return;
    }
    // If this request should not be tracked based on the
    // percentage of requests to be tracked calculation
    // then return without saving to db.
    if (!$this->shouldTrackRequest()) {
      $this->debugLog('Skip');
      return;
    }
    global $_website_speed_timer;
    $uid = $this->currentUser->id();
    $url = $this->requestStack->getCurrentRequest()->getRequestUri();
    // Replace ids from URLs.
    $masked_url = preg_replace(
      ['/\/\d+\//', '/\/\d+$/', '/\/$/'],
      ['/NNNN/', '/NNNN', ''],
      $url
    );
    // Restore / as the masked URL for / as above regex
    // will replace it to be blank.
    if ($url == '/') {
      $masked_url = '/';
    }
    $index_timed = isset($_website_speed_timer) ? 1 : 0;
    $created = $this->requestStack
      ->getCurrentRequest()->server
      ->get('REQUEST_TIME');
    // Record the page speed for this request.
    // This is done after response is sent and hence
    // should not have an impact on page speed.
    $this->database->insert('website_speed_timings')
      ->fields([
        'url' => $url,
        'masked_url' => $masked_url,
        'route_name' => $route,
        'uid' => $uid,
        'index_timed' => $index_timed,
        'response_code' => $this->responseCode,
        'response_start' => $this->timer['response'] - $this->timer['start'],
        'kernel_terminate' => $this->timer['terminate'] - $this->timer['start'],
        'created' => $created,
      ])
      ->execute();
    $this->saved = TRUE;
  }

  /**
   * Check if this request has to be tracked probabilistically.
   *
   * Use the config for the percentage of requests to be tracked
   * and check if this request has to be tracked. The function
   * uses a time based sampling to achieve this.
   */
  public function shouldTrackRequest() {
    $perc = $this->configFactory->get('website_speed.settings')->get('perc_tracked');
    $limit = $perc * 1000;
    $rand = microtime(TRUE);
    $rand = round($rand * 100000, 0) % 100000;
    if ($rand < $limit) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Helper function to log in watchdog.
   *
   * @param string $type
   *   Type of debug log
   *     Resp - track time for response event
   *     Term - track time for terminate event
   *     Skip - track time for terminate event when skipped.
   */
  private function debugLog($type) {
    if ($this->debug) {
      $url = $this->requestStack->getCurrentRequest()->getRequestUri();
      $now = microtime(TRUE);
      $time_taken = $now - $this->timer['start'];
      $time_taken = round($time_taken * 100000, 0) / 100000;
      $logger = $this->loggerFactory->get('website_speed');
      $logger->debug($type . ": " . $time_taken . " - " . $url);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    // At the start of boot.
    $events[KernelEvents::REQUEST][] = ['websiteSpeedOnBoot', 10000];
    // At the start of response.
    $events[KernelEvents::RESPONSE][] = ['websiteSpeedOnResponseStart', 10000];
    // On terminating this request.
    $events[KernelEvents::TERMINATE][] = ['websiteSpeedOnTerminate', -10000];
    return $events;
  }

}
