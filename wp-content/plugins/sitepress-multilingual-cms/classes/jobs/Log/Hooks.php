<?php

namespace WPML\TM\Jobs\Log;

use WPML\TM\Jobs\JobLog;

class Hooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const SUBMENU_HANDLE = 'wpml-tm-job-log';

	/** @var ViewFactory $viewFactory */
	private $viewFactory;

	public function __construct( ViewFactory $viewFactory ) {
		$this->viewFactory = $viewFactory;
	}

	public function add_hooks() {
		add_action( 'admin_menu', [ $this, 'addLogSubmenuPage' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueScripts' ] );
		add_action( 'wp_ajax_wpml_tm_job_log_toggle_feature', [ $this, 'handleAjaxToggle' ] );
		add_action( 'wp_ajax_wpml_tm_job_log_clear', [ $this, 'handleAjaxClear' ] );
		add_action( 'wp_ajax_wpml_tm_job_log_download', [ $this, 'handleAjaxDownload' ] );
		add_action( 'wp_ajax_wpml_tm_job_log_download_last_send', [ $this, 'handleAjaxDownloadLastSend' ] );
	}

	public function addLogSubmenuPage() {
		$x = WPML_PLUGIN_FOLDER . '/menu/support.php';
		add_submenu_page(
			WPML_PLUGIN_FOLDER . '/menu/support.php',
			__( 'Translation Management Job Logs', 'sitepress' ),
			__( 'TM job logs', 'sitepress' ),
			'manage_options',
			self::SUBMENU_HANDLE,
			[ $this, 'renderPage' ]
		);
	}

	public function renderPage() {
		$this->viewFactory->create()->renderPage();
	}

	public function enqueueScripts() {
		if ( isset( $_GET['page'] ) && $_GET['page'] === self::SUBMENU_HANDLE ) {
			wp_enqueue_style(
				'wpml-tm-job-log',
				WPML_TM_URL . '/res/css/job-log.css',
				array(),
				ICL_SITEPRESS_SCRIPT_VERSION
			);

			wp_enqueue_script(
				'support-tm-logs',
				WPML_TM_URL . '/res/js/support-tm-logs.js',
				array( 'jquery' ),
				ICL_SITEPRESS_SCRIPT_VERSION,
				true
			);
			wp_localize_script(
				'support-tm-logs',
				'wpmlTmJobLog',
				array(
					'ajaxUrl'                => admin_url( 'admin-ajax.php' ),
					'nonce'                  => wp_create_nonce( 'wpml_tm_job_log' ),
					'confirmClearLogs'       => __( 'Are you sure you want to clear all job logs?', 'sitepress' ),
					'logsClearedSuccess'     => __( 'Logs cleared successfully', 'sitepress' ),
					'logsClearedFailed'      => __( 'Failed to clear logs', 'sitepress' ),
					'logsClearedError'       => __( 'Error clearing logs', 'sitepress' ),
				)
			);
		}
	}

	public function handleAjaxToggle() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpml_tm_job_log' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$enabled = isset( $_POST['enabled'] ) ? (bool) intval( $_POST['enabled'] ) : false;
		JobLog::setIsEnabled( $enabled );

		wp_send_json_success( array(
			'enabled' => $enabled,
		) );
	}

	public function handleAjaxClear() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpml_tm_job_log' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$result = JobLog::clearLogs();

		if ( $result ) {
			wp_send_json_success( array(
				'message' => __( 'Logs cleared successfully', 'sitepress' ),
			) );
		} else {
			wp_send_json_error( __( 'Failed to clear logs', 'sitepress' ) );
		}
	}

	public function handleAjaxDownload() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpml_tm_job_log' ) ) {
			wp_die( 'Invalid nonce' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}
		if (
			isset($_POST['loguid']) &&
			preg_match('/^[0-9a-f]{13}$/i', $_POST['loguid'])
		) {
			$logUid = $_POST['loguid'];
		} else {
			wp_die( 'Wrong loguid.' );
		}

		$logs = JobLog::getLogs();
		$log  = null;
		foreach ( $logs as $logItem ) {
			if ( $logItem['logUid'] === $logUid ) {
				$log = $logItem;
				break;
			}
		}

		if ( ! $log ) {
			wp_die( 'Log not found' );
		}

		$text = $this->generateLogText( $log );

		$filename = 'job-log-' . sanitize_file_name( $log['requestDateTime'] ) . '.txt';

		// Set headers for download
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );

		echo $text;
		exit;
	}

	public function handleAjaxDownloadLastSend() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpml_tm_job_log' ) ) {
			wp_die( 'Invalid nonce' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$logs = JobLog::getLogs();

		if ( empty( $logs ) ) {
			wp_die( 'No logs found' );
		}

		// Find the last send-to-translation request and collect all related logs
		$operationLogs = $this->getLastTranslateContentOperationLogs( $logs );

		if ( empty( $operationLogs ) ) {
			wp_die( 'No "Translate Content" operation logs found' );
		}

		$text = $this->generateOperationLogText( $operationLogs );

		$filename = 'translate-content-operation-' . sanitize_file_name( $operationLogs[0]['requestDateTime'] ) . '.txt';

		// Set headers for download
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );

		echo $text;
		exit;
	}

	private function getLastTranslateContentOperationLogs( $logs ) {
		// Find the index of the last send-to-translation request
		$lastSendIndex = null;
		foreach ( $logs as $index => $log ) {
			if ( $this->isSendToTranslationRequest( $log['requestUrl'] ) ) {
				$lastSendIndex = $index;
				break; // Logs are ordered newest first, so first match is the last operation
			}
		}

		if ( $lastSendIndex === null ) {
			return [];
		}

		// Collect all logs from the last send-to-translation onwards (any sequence of logs is ok)
		// Logs are ordered newest first, so we go backwards from lastSendIndex to 0
		$operationLogs = [];
		for ( $i = $lastSendIndex; $i >= 0; $i-- ) {
			$operationLogs[] = $logs[ $i ];
		}

		return $operationLogs;
	}

	private function isSendToTranslationRequest( $url ) {
		return substr( $url, -strlen( 'send-to-translation' ) ) === 'send-to-translation';
	}

	private function isSyncRequest( $url ) {
		return substr( $url, -strlen( 'sync' ) ) === 'sync';
	}

	private function isDownloadRequest( $url ) {
		return substr( $url, -strlen( 'download' ) ) === 'download';
	}

	private function generateOperationLogText( $operationLogs ) {
		$text = "";

		$text .= "########################################\n";
		$text .= "# WPML 'Translate Content' Operation Log\n";
		$text .= "########################################\n\n";

		$text .= "========================================\n";
		$text .= "WPML TRANSLATION OPERATION\n";
		$text .= "========================================\n\n";
		$text .= "operation_type: send_to_translation\n";
		$text .= "operation_scope: single-operation-buffer\n";
		$text .= "trigger: Translation Management → Send to translation\n";
		$text .= "started_at: " . ( ! empty( $operationLogs ) ? $operationLogs[0]['requestDateTime'] : 'N/A' ) . "\n";
		$text .= "operation_context_source: Request 1 Request Parameters\n";
		$text .= "expected_result:\n";
		$text .= "  - ATE jobs may be created across multiple send_to_translation requests\n";
		$text .= "  - All created ATE jobs must eventually be downloaded\n";
		$text .= "  - Successful completion is defined as download status = 10(ICL_TM_COMPLETE) in the download response for each ATE job\n";
		$text .= "notes:\n";
		$text .= "  - One log file represents exactly one translation attempt\n\n";

		$requestNum = 0;
		foreach ( $operationLogs as $log ) {
			$requestNum++;
			$url = $log['requestUrl'];

			$text .= "========================================\n";
			$text .= "REQUEST " . $requestNum . "\n";
			$text .= "========================================\n";

			if ( $this->isSendToTranslationRequest( $url ) ) {
				$text .= "request_action: send_to_translation\n";
				$text .= "endpoint: " . $log['requestUrl'] . "\n";
				$text .= "started_at: " . $log['requestDateTime'] . "\n";
				$text .= "description: User clicked \"Send to translation\" in the Translation Management dashboard.\n";
			} elseif ( $this->isSyncRequest( $url ) ) {
				$text .= "request_action: sync\n";
				$text .= "endpoint: " . $log['requestUrl'] . "\n";
				$text .= "started_at: " . $log['requestDateTime'] . "\n";
				$text .= "description: Endpoint to check if translations are available for download from external translation editor ATE was called.\n";
			} elseif ( $this->isDownloadRequest( $url ) ) {
				$text .= "request_action: download\n";
				$text .= "endpoint: " . $log['requestUrl'] . "\n";
				$text .= "started_at: " . $log['requestDateTime'] . "\n";
				$text .= "description: Endpoint to download translations from external translation editor ATE was called.\n";
			}

			$text .= "\n";
			$text .= $this->generateLogText( $log );
			$text .= "\n\n";
		}

		$text .= "########################################\n";
		$text .= "# End of Operation Log\n";
		$text .= "########################################\n";

		return $text;
	}

	private function generateLogText( $log ) {
		$text = "";

		// Request info
		$text .= "Date/Time: " . $log['requestDateTime'] . "\n";
		$text .= "URL: " . $log['requestUrl'] . "\n\n";

		// Request parameters
		$text .= "Request Parameters:\n";
		$text .= "------------------\n";
		$text .= wp_json_encode( $log['requestParams'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n\n";

		// Process log groups
		if ( ! empty( $log['logsByGroup'] ) && is_array( $log['logsByGroup'] ) ) {
			$action_num = 0;
			foreach ( $log['logsByGroup'] as $group ) {
				$action_num++;
				$text .= "========================================\n";
				$text .= "Action " . $action_num . ": " . $group['label'] . "\n";
				$text .= "========================================\n\n";

				if ( ! empty( $group['logs'] ) && is_array( $group['logs'] ) ) {
					$step_num = 0;
					foreach ( $group['logs'] as $log_item ) {
						$step_num++;
						$text .= "Step " . $step_num . ": " . ( $log_item['id'] ?? 'N/A' ) . "\n";
						$text .= str_repeat( '-', 40 ) . "\n";

						if ( isset( $log_item['data'] ) ) {
							$text .= wp_json_encode( $log_item['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n\n";
						}
					}
				}
			}
		}

		$text .= "========================================\n";
		$text .= "End of Log\n";
		$text .= "========================================\n";

		return $text;
	}
}
