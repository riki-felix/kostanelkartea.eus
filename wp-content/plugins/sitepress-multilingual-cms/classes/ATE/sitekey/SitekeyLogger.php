<?php

namespace WPML\TM\ATE\Sitekey;

use WPML\TM\ATE\Log\Storage;
use WPML\TM\ATE\Log\Entry;
use WPML\TM\ATE\Log\EventsTypes;

class SitekeyLogger {

	/** @var SitekeyProvider */
	private $sitekeyProvider;

	public function __construct( SitekeyProvider $sitekeyProvider ) {
		$this->sitekeyProvider = $sitekeyProvider;
	}

	public function logError( $message ) {
		Storage::add( 
			Entry::createForType(
				EventsTypes::SERVER_AMS,
				[
					'error' => $message,
					'sitekey' => $this->getMaskedSitekey()
				]
			)
		);
	}

	private function getMaskedSitekey() {
		$sitekey = $this->sitekeyProvider->getSitekey();
		$lastDigitsCount = 4;
		
		return $sitekey ?
			str_repeat( 'x', strlen( $sitekey ) - $lastDigitsCount ) . substr( $sitekey, -$lastDigitsCount ) :
			'empty';
	}
}
