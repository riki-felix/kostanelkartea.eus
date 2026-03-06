<?php

namespace WPML\TM\ATE\ClonedSites\SetupMigration\Resetter;

use WPML\TM\ATE\ClonedSites\SetupMigration\SiteKeyRemoveServiceFactory;

class SiteKeyCleaner {

	/** @var SiteKeyRemoveServiceFactory */
	private $siteKeyRemoveServiceFactory;

	/**
	 * @param SiteKeyRemoveServiceFactory $siteKeyRemoveServiceFactory
	 */
	public function __construct( SiteKeyRemoveServiceFactory $siteKeyRemoveServiceFactory ) {
		$this->siteKeyRemoveServiceFactory = $siteKeyRemoveServiceFactory;
	}

	/**
	 * Unregisters the WPML site key from the installer.
	 *
	 * @return void
	 */
	public function unregister() {
		$removeService = $this->siteKeyRemoveServiceFactory->create();

		if ( ! $removeService ) {
			return;
		}

		// Remove the site key without notifying external API (site is cloned/moved)
		$removeService->remove( 'wpml', false );

		// Also clear the site key from WPML's own settings (icl_sitepress_settings)
		// The OTGS installer only removes it from wp_installer_settings_common
		icl_set_setting( 'site_key', null, true );
	}
}
