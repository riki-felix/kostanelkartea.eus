<?php

namespace WPML\UserInterface\Web\Core\Component\ContentStats\Application\Endpoint\CalculateContentStats;

use WPML\Core\Component\ReportContentStats\Application\Service\ContentStatsService;
use WPML\Core\Component\ReportContentStats\Application\Service\ContentStatsServiceException;
use WPML\Core\Component\ReportContentStats\Application\Service\EventReasonService;
use WPML\Core\Component\ReportContentStats\Application\Service\LastSentService;
use WPML\Core\Component\ReportContentStats\Application\Service\ProcessingLockService;
use WPML\Core\Component\ReportContentStats\Application\Service\ReportPreparer\ReportPreparerService;
use WPML\Core\Component\ReportContentStats\Application\Service\ReportSender\ReportSenderService;
use WPML\Core\Component\ReportContentStats\Application\Service\RetryService;
use WPML\Core\Port\Endpoint\EndpointInterface;

class ProcessContentStatsController implements EndpointInterface {

  /** @var LastSentService */
  private $lastSentService;

  /** @var ContentStatsService */
  private $contentStatsService;

  /** @var ReportPreparerService */
  private $reportPreparerService;

  /** @var ReportSenderService */
  private $reportSenderService;

  /** @var RetryService */
  private $retryService;

  /** @var ProcessingLockService */
  private $processingLockService;

  /** @var EventReasonService */
  private $eventReasonService;


  public function __construct(
    ContentStatsService $contentStatsService,
    LastSentService $lastSentService,
    ReportPreparerService $reportPreparerService,
    ReportSenderService $reportSenderService,
    RetryService $retryService,
    ProcessingLockService $processingLockService,
    EventReasonService $eventReasonService
  ) {
    $this->contentStatsService    = $contentStatsService;
    $this->lastSentService        = $lastSentService;
    $this->reportPreparerService  = $reportPreparerService;
    $this->reportSenderService    = $reportSenderService;
    $this->retryService           = $retryService;
    $this->processingLockService  = $processingLockService;
    $this->eventReasonService     = $eventReasonService;
  }


  public function handle( $requestData = null ): array {
    /** @var string|null $ownerId */
    $ownerId = $requestData['ownerId'] ?? null;

    if ( $this->processingLockService->isLockedByOthers( $ownerId ) ) {
      return [
        'success' => false,
        'locked'  => true,
        'message' => 'Content stats processing is already running in another tab/session',
      ];
    }

    if ( ! $ownerId ) {
      $ownerId = $this->processingLockService->acquire();
      if ( ! $ownerId ) {
        return [
          'success' => false,
          'locked'  => true,
          'message' => 'Content stats processing is already running in another tab/session',
        ];
      }
    }

    $this->processingLockService->refresh( $ownerId );

    try {
      $processedPostTypes = $this->contentStatsService->processPostTypes();

      /**
       * In case $processedPostTypes is not FALSE, we return response and in next
       * iteration we decide if we should process more post types or send report.
       */

      if ( $processedPostTypes ) {
        return [
          'success' => true,
          'ownerId' => $ownerId,
          'message' => 'Calculation done for post types: ' .
                       implode( ',', $processedPostTypes ),
        ];
      }

      /**
       * In case we don't have any post types to process anymore
       * we should start sending report and setting correct values in DB.
       */

      // Prepare report
      $preparedReport = $this->reportPreparerService->prepare();

      // Sending the report
      $reportSentSuccessfully = $this->reportSenderService->send( $preparedReport );

      if ( ! $reportSentSuccessfully ) {
        $this->retryService->incrementAttempt();

        if ( $this->retryService->hasExceededMaxAttempts() ) {
          // Give up after max attempts, update last sent and reset data
          $this->finalizeReportingCycle();

          return [
            'success' => false,
            'message' => 'Error when sending report - max retry attempts exceeded, will try again in 30 days',
          ];
        }

        return [
          'success' => false,
          'ownerId' => $ownerId,
          'message' => 'Error when sending report - will retry in ' .
                       $this->retryService->getRetryIntervalMinutes() . ' minutes',
        ];
      }

      $this->finalizeReportingCycle();
    } catch ( ContentStatsServiceException $e ) {
      return [
        'success' => false,
        'message' => $e->getMessage(),
      ];
    }

    return [
      'success' => true,
      'message' => 'Report sent successfully!',
    ];
  }


  /**
   * @return void
   */
  private function finalizeReportingCycle() {
    $this->retryService->reset();
    $this->lastSentService->update( time() );
    $this->contentStatsService->resetPostTypesStatsData();
    $this->processingLockService->release();
    $this->eventReasonService->clear();
  }


}
