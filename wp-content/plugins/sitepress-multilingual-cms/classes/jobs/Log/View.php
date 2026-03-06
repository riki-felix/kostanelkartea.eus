<?php

namespace WPML\TM\Jobs\Log;

use WPML\Collect\Support\Collection;
use WPML\TM\Jobs\JobLog;

class View {

	/** @var Collection $logs */
	private $logs;

	/** @var bool */
	private $isLoggingEnabled;

	public function __construct( Collection $logs, $isLoggingEnabled ) {
		$this->logs = $logs;
		$this->isLoggingEnabled = $isLoggingEnabled;
	}

	public function renderPage() {
		$isEnabled = $this->isLoggingEnabled;
		$label     = $isEnabled ? esc_html__( 'Job logs are enabled', 'sitepress' ) : esc_html__( 'Job logs are disabled', 'sitepress' );

		$textEnabled  = esc_attr__( 'Job logs are enabled', 'sitepress' );
		$textDisabled = esc_attr__( 'Job logs are disabled', 'sitepress' );
		?>
		<div class="wrap wpml-tm-job-log">
			<h1><?php esc_html_e( 'Translation Management Job Logs', 'sitepress' ); ?></h1>
			<br>
			<div style="display: flex; justify-content: space-between">
				<div class="job-log-settings-toggle-wrapper">
					<label class="job-log-settings-toggle">
						<input type="checkbox" class="job-log-toggle-input" id="job-log-feature-toggle" <?php checked( $isEnabled ); ?> />
						<span class="job-log-toggle-slider"></span>
					</label>
					<span class="job-log-toggle-label" data-textenabled="<?php echo $textEnabled; ?>" data-textdisabled="<?php echo $textDisabled; ?>"><?php echo $label; ?></span>
					<span class="job-log-toggle-loader spinner" style="display: none; float: none; margin: 0 0 0 8px;"></span>
				</div>
				<div class="job-log-clear-wrapper">
					<button type="button" class="button" id="job-log-download-last-send-button"><?php esc_html_e( 'Download last ‘Translate Content’ action log', 'sitepress' ); ?></button>
					<button type="button" class="button" id="job-log-clear-button"><?php esc_html_e( 'Clear logs', 'sitepress' ); ?></button>
					<span class="job-log-clear-loader spinner" style="display: none; float: none; margin: 0 0 0 8px;"></span>
					<span class="job-log-clear-message" style="margin-left: 10px; color: #46b450;"></span>
				</div>
			</div>
			<table class="wp-list-table widefat fixed striped posts">
				<thead><?php $this->renderTableHeader(); ?></thead>

				<tbody id="the-list">
				<?php
				if ( $this->logs->isEmpty() ) {
					$this->renderEmptyTable();
				} else {
					$this->logs->each( [ $this, 'renderTableRow' ] );
				}
				?>
				</tbody>
				<tfoot><?php $this->renderTableHeader(); ?></tfoot>
			</table>
		</div>
		<?php
	}

	private function renderTableHeader() {
		?>
		<tr>
			<th class="description">
				<span><?php esc_html_e( 'Logs', 'sitepress' ); ?></span>
			</th>
		</tr>
		<?php
	}

	public function renderTableRow( $request, $i ) {
		$requestUrl       = $request['requestUrl'];
		$requestSectionId = 'target-request' . $i . uniqid();
		$requestLabel     = '';

		$isTranslationRequest = substr( $requestUrl, -( strlen( 'send-to-translation' ) ) ) === 'send-to-translation';
		$isSyncRequest        = substr( $requestUrl, -( strlen( 'sync' ) ) ) === 'sync';
		$isDownloadRequest    = substr( $requestUrl, -( strlen( 'download' ) ) ) === 'download';

		if ( $isTranslationRequest ) {
			$requestLabel = __( 'Send to translation', 'sitepress' );
		} else if ( $isSyncRequest ) {
			$requestLabel = __( 'Sync', 'sitepress' );
		} else if ( $isDownloadRequest ) {
			$requestLabel = __( 'Download', 'sitepress' );
		}

		$errorClass = ! empty( $request['hasErrorLogs'] ) ? ' job-log-url-error' : '';

		?>
		<tr>
			<td class="description">
				<div style="display: flex; justify-content: space-between">
					<div>
						<?php echo '<span class="job-log-url' . $errorClass . '">' . esc_html( $requestLabel ) . '</span> '; ?>
						<?php echo '<span class="job-log-label">' . esc_html( $request['requestDateTime'] . ' ' . $request['requestUrl'] ) . '</span>'; ?>
						<?php echo $this->getToggleButtonHtml( $requestSectionId ); ?>
					</div>
					<div>
						<?php echo $this->getDownloadButtonHtml( $request['logUid'] ); ?>
					</div>
				</div>
				<div style="padding-left: 10px;">
					<?php
						echo '<div>';
						echo '<pre>';
						echo esc_html( (string) wp_json_encode( $request['requestParams'], JSON_PRETTY_PRINT ) );
						echo '</pre>';
						echo '</div>';
					?>
				</div>
				<?php
				echo '<div id="' . esc_attr( $requestSectionId ) . '" style="display: none; padding-left: 30px">';
				if ( ! empty( $request['logsByGroup'] ) && is_array( $request['logsByGroup'] ) ) {
					$i = 0;
					foreach ( $request['logsByGroup'] as $groupLogs ) {
						$i++;
						echo '<div style="padding-bottom: 20px">';
						echo '<strong style="text-decoration: underline">' . esc_html__( 'Action', 'sitepress' ) . ' ' . $i . ': ' . esc_html( $groupLogs['label'] ) . '</strong><br>';
						echo '<div style="padding-left: 20px">';
						if ( JobLog::isSendJobsLogsGroup( $groupLogs['groupId'] ) ) {
							$this->renderSendJobsLogs( $groupLogs['logs'] );
						} else {
							$j = 0;
							while ( $j < count( $groupLogs['logs'] ) ) {
								$j = $this->renderLog( $j, $groupLogs['logs'], $groupLogs['logs'][ $j ] );
							}
						}
						echo '</div>';
						echo '</div>';
					}
				} else {
					echo esc_html__( 'No logs available', 'sitepress' );
				}
				echo '</div>';
				?>
			</td>
		</tr>
		<?php
	}

	private function renderSendJobsLogs( $logs ) {
		$i = 0;
		while ( $i < count( $logs ) ) {
			$log = $logs[ $i ];
			if  ( ! isset( $log['element_id'] ) ) {
				$i = $this->renderLog( $i, $logs, $log );
				continue;
			}

			$elementId = $log['element_id'];
			$postfix   = '';

			if ( isset( $log['string_ids_in_batch'] ) ) {
				$postfix =  ', (string ids in batch: ' . implode( ', ', $log['string_ids_in_batch'] ) . ')';
			}

			$sectionId = 'element-' . $elementId . '-' . uniqid();
			echo '<div>';
			echo '<span class="job-log-label job-log-sublabel">' . $this->getStepHtml( $i ) . __( 'Processing element with id', 'sitepress' ) . ' `' . $elementId . '`', $postfix . '`</span> ';
			echo $this->getToggleButtonHtml( $sectionId );
			echo '</div>';
			echo '<div id="' . esc_attr( $sectionId ) . '" data-element-id="' . esc_attr( $elementId ) . '" style="display: none; padding-left: 20px;">';
			do {
				$i = $this->renderSendJobsLogForElement( $i, $logs, $logs[ $i ] );
			} while (
				$i < count( $logs )
				&& isset( $logs[$i]['element_id'] )
				&& $logs[$i]['element_id'] === $elementId
			);
			echo '</div>';
		}
	}

	private function renderSendJobsLogForElement( $i, $logs, $log ) {
		if  ( ! isset( $log['target_lang'] ) ) {
			$i = $this->renderLog( $i, $logs, $log );
			return $i;
		}

		$elementId  = $log['element_id'];
		$targetLang = $log['target_lang'];

		$sectionId = 'target-' . $elementId . '-' . $targetLang . '-' . uniqid();
		echo '<div>';
		echo '<span class="job-log-label job-log-sublabel">' .  $this->getStepHtml( $i ) . __( 'Processing target language', 'sitepress' ) . ' `' . $targetLang . '`</span> ';
		echo $this->getToggleButtonHtml( $sectionId );
		echo '</div>';
		echo '<div id="' . esc_attr( $sectionId ) . '" data-element-id="' . esc_attr( $elementId ) . '" data-target-lang="' . esc_attr( $targetLang ) . '" style="display: none; padding-left: 20px">';
		do {
			$i = $this->renderLog( $i, $logs, $logs[ $i ] );
		} while (
			$i < count( $logs )
			&& isset( $logs[$i]['element_id'] )
			&& $logs[$i]['element_id'] === $elementId
			&& isset( $logs[$i]['target_lang'] )
			&& $logs[$i]['target_lang'] === $targetLang
		);
		echo '</div>';

		return $i;
	}

	private function renderEmptyTable() {
		?>
		<tr>
			<td colspan="" class="title column-title has-row-actions column-primary">
				<?php esc_html_e( 'No entries', 'sitepress' ); ?>
			</td>
		</tr>
		<?php
	}

	private function maybeJsonStringsToArray( $data ) {
		foreach ( $data as $key => $value ) {
			if ( $key === 'body' && is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$data[ $key ] = $decoded;
				}
			}

			if ( is_array( $value ) ) {
				$data[ $key ] = $this->maybeJsonStringsToArray( $value );
			}
		}

		return $data;
	}

	private function getToggleButtonHtml( $section_id ) {
		$text_open  = esc_attr__( 'Open', 'sitepress' );
		$text_close = esc_attr__( 'Close', 'sitepress' );
		$section_id = esc_attr( $section_id );

		return sprintf(
			'<button class="button tm-log-toggle" data-textopen="%1$s" data-textclose="%2$s" data-target="%3$s">%4$s</button>',
			$text_open,
			$text_close,
			$section_id,
			esc_html__( 'Open', 'sitepress' )
		);
	}

	private function renderLog( $i, $logs, $log ) {
		if  ( ! isset( $log['apiCall'] ) ) {
			$this->renderLogItem( $log, $i );
			return ++$i;
		}

		$url      = $log['apiCall'];
		$urlParts = explode( '?', $url );
		$url      = $urlParts[0];

		$sectionId = 'target-log' . uniqid();
		echo '<div>';
		echo '<span class="job-log-label job-log-sublabel">' .  $this->getStepHtml( $i, JobLog::isErrorLog( $log ) ) . __ ( 'Api call to url', 'sitepress' ) . ' `' . $url . '`</span> ';
		echo $this->getToggleButtonHtml( $sectionId );
		echo '</div>';
		echo '<div id="' . esc_attr( $sectionId ) . '" style="display: none">';
		do {
			$this->renderLogItem( $logs[ $i ], $i, 'padding-left: 20px', [
				'padding-left' => '40px',
			] );
			$i++;
		} while (
			$i < count( $logs )
			&& isset( $logs[$i]['apiCall'] )
		);
		echo '</div>';

		return $i;
	}

	private function renderLogItem( $log, $i, $rootCss = '', $logCss = [] ) {
		$defaultLogCss = [
			'padding-left' => '20px',
		];
		$logCss = array_merge( $defaultLogCss, $logCss );

		$logCssStr = '';
		foreach ( $logCss as $key => $value ) {
			$logCssStr .= $key . ': ' . $value . ';';
		}

		$sectionId = 'target-log-item' . $i . uniqid();
		echo '<div style="' . $rootCss . '">';
		echo '<span class="job-log-label job-log-sublabel">' . $this->getStepHtml( $i, JobLog::isErrorLog( $log ) ) . esc_html( $log['id'] ) . '</span> ';
		echo '<button class="button tm-log-toggle tm-log-stack-trace-toggle" data-target="' . esc_attr( $sectionId ) . '">' . esc_html__( 'View trace', 'sitepress' ) . '</button>';
		echo '</div>';
		echo '<div id="' . esc_attr( $sectionId ) . '" style="display: none">';
		echo '<pre>';
		echo esc_html( (string) wp_json_encode( $this->maybeJsonStringsToArray( $log['trace'] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		echo '</pre>';
		echo '</div>';
		unset( $log['trace'] );
		echo '<pre style="' . $logCssStr . '">';
		echo esc_html( (string) wp_json_encode( $this->maybeJsonStringsToArray( $log['data'] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		echo '</pre>';
	}

	private function getStepHtml( $i, $error = false ) {
		$class = $error ? ' job-log-step-error' : '';
		return '<span class="job-log-step' . $class . '">' . __( 'Step', 'sitepress' ) . ' ' . ($i + 1) . ':' . '</span> ';
	}

	private function getDownloadButtonHtml( $logUid ) {
		return sprintf(
			'<button class="button tm-log-download" data-loguid="' . esc_attr( $logUid ) . '">%1$s</button>',
			esc_html__( 'Download', 'sitepress' )
		);
	}
}
