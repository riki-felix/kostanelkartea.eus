<?php

namespace WPML\TM\ATE\ClonedSites\SetupMigration;

class SiteKeyRemoveServiceFactory {

	/**
	 * Creates an instance of OTGS_Installer_Site_Key_Remove_Service if available.
	 *
	 * @return \OTGS_Installer_Site_Key_Remove_Service|null
	 */
	public function create() {
		if ( ! function_exists( 'OTGS_Installer' ) || ! class_exists( 'OTGS_Installer_Site_Key_Remove_Service' ) ) {
			return null;
		}

		$installer = \OTGS_Installer();
		if ( ! $installer ) {
			return null;
		}

		// Use the factory to create repositories with proper dependencies
		$repositoriesFactory = new \OTGS_Installer_Repositories_Factory();
		$repositories = $repositoriesFactory->create( $installer );
		$removeRequest = new \OTGS_Installer_Site_Key_Remove_Request();

		return new \OTGS_Installer_Site_Key_Remove_Service( $repositories, $removeRequest );
	}
}
