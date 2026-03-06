<?php

namespace WPML\PostHog\State;

use WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository\PostHogStateRepository;
use WPML\Infrastructure\WordPress\Port\Persistence\Options;

class PostHogState {

	public static function isEnabled() {
		return ( new PostHogStateRepository( new Options() ) )->isEnabled();
	}
}
