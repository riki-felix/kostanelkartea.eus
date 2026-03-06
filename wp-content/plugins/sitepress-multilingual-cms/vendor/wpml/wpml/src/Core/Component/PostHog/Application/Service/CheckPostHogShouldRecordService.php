<?php

namespace WPML\Core\Component\PostHog\Application\Service;

use WPML\Core\Component\PostHog\Application\Repository\PostHogDefaultRequestSentRepositoryInterface;
use WPML\Core\Component\PostHog\Application\Repository\PostHogStateRepositoryInterface;
use WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSiteKeyQueryInterface;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Application\Service\PostHogRecording\PostHogRecordingService;

class CheckPostHogShouldRecordService {

    /** @var WpmlSiteKeyQueryInterface */
    private $siteKeyQuery;

    /** @var PostHogRecordingService */
    private $postHogRecordingService;

    /** @var PostHogStateRepositoryInterface */
    private $postHogStateRepository;

    /** @var PostHogDefaultRequestSentRepositoryInterface */
    private $postHogDefaultRequestSentRepository;

    /** @var RetryService */
    private $retryService;


  public function __construct(
        WpmlSiteKeyQueryInterface $siteKeyQuery,
        PostHogRecordingService $postHogRecordingService,
        PostHogStateRepositoryInterface $postHogStateRepository,
        PostHogDefaultRequestSentRepositoryInterface $postHogDefaultRequestSentRepository,
        RetryService $retryService
    ) {
      $this->siteKeyQuery                        = $siteKeyQuery;
      $this->postHogRecordingService             = $postHogRecordingService;
      $this->postHogStateRepository              = $postHogStateRepository;
      $this->postHogDefaultRequestSentRepository = $postHogDefaultRequestSentRepository;
      $this->retryService                        = $retryService;
  }


  public function run(): bool {
      $siteKey = $this->siteKeyQuery->get();

    if ( ! $siteKey ) {
        return false;
    }

      // Try to acquire the lock atomically
      // This prevents race conditions from multiple concurrent requests
    if ( ! $this->postHogDefaultRequestSentRepository->tryAcquireLock() ) {
        // Lock already acquired by another request, skip
        return true;
    }

      $result = $this->postHogRecordingService->run( $siteKey );
      $this->postHogStateRepository->setIsEnabled( $result['shouldRecord'] );

      // If API call failed, release the lock to allow retry
    if ( $result['isResponseError'] ) {
        $this->handleResponseError();
        return false;
    }

      $this->retryService->reset();
      return true;
  }


  /** @return void */
  public function handleResponseError() {
      $this->retryService->incrementAttempt();

    if ( $this->retryService->hasExceededMaxAttempts() ) {
        $this->retryService->reset();
    } else {
        $this->postHogDefaultRequestSentRepository->delete();
    }
  }


}
