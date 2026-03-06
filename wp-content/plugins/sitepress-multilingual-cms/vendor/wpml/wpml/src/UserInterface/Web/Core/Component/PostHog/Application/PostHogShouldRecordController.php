<?php

namespace WPML\UserInterface\Web\Core\Component\PostHog\Application;

use WPML\Core\Component\PostHog\Application\Service\CheckPostHogShouldRecordService;
use WPML\Core\Component\PostHog\Application\Service\RetryService;
use WPML\Core\Port\Endpoint\EndpointInterface;

class PostHogShouldRecordController implements EndpointInterface {

  /** @var CheckPostHogShouldRecordService */
  private $checkPostHogShouldRecordService;

  /** @var RetryService */
  private $retryService;


  public function __construct(
    CheckPostHogShouldRecordService $checkPostHogShouldRecordService,
    RetryService $retryService
  ) {
    $this->checkPostHogShouldRecordService = $checkPostHogShouldRecordService;
    $this->retryService                    = $retryService;
  }


  public function handle( $requestData = null ): array {

    if ( $this->retryService->isInRetryMode() ) {
      return $this->handleRetry();
    }

    $result = $this->checkPostHogShouldRecordService->run();

    return [ 'success' => $result ];
  }


  /**
   * @return array{
   *   success: bool,
   *   message: string
   * }
   */
  private function handleRetry() {
    if ( $this->retryService->shouldRetry() ) {
      $result = $this->checkPostHogShouldRecordService->run();

      return [ 'success' => $result, 'message' => 'retry attempted' ];
    }

    if ( $this->retryService->hasExceededMaxAttempts() ) {
      return [
        'success' => false,
        'message' => 'Failed! reached max retry attempts'
      ];
    }

    return [
      'success' => false,
      'message' => 'will retry later in ' . $this->retryService->getRetryIntervalMinutes() . ' minutes'
    ];
  }


}
