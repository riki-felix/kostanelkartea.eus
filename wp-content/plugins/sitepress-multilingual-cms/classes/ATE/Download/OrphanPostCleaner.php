<?php

namespace WPML\TM\ATE\Download;

use WPML\TM\ATE\Download\OrphanPostCleaner\OrphanPostRepository;
use WPML\TM\ATE\Download\OrphanPostCleaner\ProcessCounter;
use WPML\TM\ATE\Download\OrphanPostCleaner\Sleeper;

/**
 * Cleans up orphan posts created during failed ATE translation downloads.
 *
 * Uses a counter stored in wp_options to track concurrent download processes
 * and ensure cleanup only runs when all parallel processes have completed.
 */
class OrphanPostCleaner {

	const WAIT_INTERVAL_SECONDS = 2;
	const MAX_WAIT_RETRIES = 10;

	/** @var OrphanPostRepository */
	private $repository;

	/** @var ProcessCounter */
	private $counter;

	/** @var Sleeper */
	private $sleeper;

	/** @var int|null */
	private $maxPostIdBefore;

	/** @var bool */
	private $cleanupNeeded = false;

	public function __construct(
		OrphanPostRepository $repository,
		ProcessCounter $counter,
		Sleeper $sleeper
	) {
		$this->repository = $repository;
		$this->counter = $counter;
		$this->sleeper = $sleeper;
	}

	public function incrementProcessCounter() {
		$this->counter->increment();
	}

	public function decrementProcessCounter() {
		$this->counter->decrement();
	}

	public function recordStateBeforeInsert() {
		$this->maxPostIdBefore = $this->repository->getMaxPostId();
	}

	public function markCleanupNeeded() {
		$this->cleanupNeeded = true;
	}

	/**
	 * Attempts cleanup if needed, waiting for other parallel processes to complete.
	 */
	public function tryCleanup() {
		if ( ! $this->cleanupNeeded || $this->maxPostIdBefore === null ) {
			return;
		}

		$retries = 0;
		$counterValue = $this->counter->get();

		while ( $counterValue > 0 && $retries < self::MAX_WAIT_RETRIES ) {
			$this->sleeper->sleep( self::WAIT_INTERVAL_SECONDS );
			$retries++;
			$counterValue = $this->counter->get();
		}

		$this->doCleanup();
	}

	private function doCleanup() {
		$orphanIds = $this->repository->getOrphanPostIds( $this->maxPostIdBefore );

		foreach ( $orphanIds as $postId ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "[OrphanPostCleaner] Deleting orphan post with ID: {$postId}" );
			}
			$this->repository->deletePost( $postId );
		}

		$this->reset();
	}

	private function reset() {
		$this->maxPostIdBefore = null;
		$this->cleanupNeeded = false;
	}
}
