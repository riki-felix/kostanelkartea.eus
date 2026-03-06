<?php

namespace WPML\PostHog\Event;

use WPML\UserInterface\Web\Core\Component\PostHog\Application\Endpoint\Event\Capture\ProxyCaptureEventController;

class AJAXPostHogCaptureEvent {

	const NONCE = 'wpml_posthog_capture_nonce';

	public function addActions() {
		add_action( 'wp_ajax_wpml_posthog_proxy_capture_event', array( $this, 'handle' ), 10, 0 );
		add_action( 'wp_ajax_nopriv_wpml_posthog_proxy_capture_event', array( $this, 'handle' ), 10, 0 );
	}

	public function localizeScriptForWpmlEndpoints( $handle ) {
		wp_localize_script(
			$handle,
			'wpmlEndpoints',
			[
				'nonce' => wp_create_nonce( self::NONCE ),
				'route' => [
					'proxycaptureevent' => [
						'url' => admin_url( 'admin-ajax.php?action=wpml_posthog_proxy_capture_event' ),
					],
				],
			]
		);
	}

	public function handle() {
		// Verify nonce from X-WP-Nonce header
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( $_SERVER['HTTP_X_WP_NONCE'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Get request data from JSON body
		$raw_body     = file_get_contents( 'php://input' );
		$request_data = ( false !== $raw_body ) ? json_decode( $raw_body, true ) : null;

		// If no JSON body, try $_POST
		if ( empty( $request_data ) ) {
			$request_data = $_POST;
		}

		// Validate required fields
		if ( empty( $request_data['distinctId'] ) || empty( $request_data['eventName'] ) || ! isset( $request_data['eventData'] ) ) {
			wp_send_json_error( 'Missing required fields' );
		}

		try {
			global $wpml_dic;
			// Load ProxyCaptureEventController
			$controller = $wpml_dic->make( ProxyCaptureEventController::class );
			$result     = $controller->handle( $request_data );

			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result['message'] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}
