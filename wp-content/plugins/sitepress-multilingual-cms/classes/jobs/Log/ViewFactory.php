<?php

namespace WPML\TM\Jobs\Log;

use WPML\Collect\Support\Collection;
use function WPML\Container\make;
use WPML\TM\Jobs\JobLog;

class ViewFactory {

	public function create() {
		$logs             = JobLog::getLogs();
		$isLoggingEnabled = JobLog::isEnabled();

		return new View( new Collection( $logs ), $isLoggingEnabled );
	}
}
