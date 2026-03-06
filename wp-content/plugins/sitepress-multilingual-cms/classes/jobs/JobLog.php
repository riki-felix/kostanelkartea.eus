<?php

namespace WPML\TM\Jobs;

class JobLog {

	/**
	 * Stored request log structure.
	 *
	 * Case A: REST / ATE sync/download request
	 * [
	 *   requestUrl      => string,            // REST or admin URL
	 *   requestParams   => array,             // Parsed input params
	 *   requestDateTime => string (ISO-8601),
	 *   hasErrorLogs    => bool,
	 *   logsByGroup     => [
	 *     [
	 *       groupId => int,
	 *       label   => string,
	 *       logs    => [
	 *         [
	 *           id      => string,             // Log message
	 *           data    => array,              // Arbitrary payload
	 *           trace   => string[],           // Call stack (file:line class/method)
	 *           logType => int,                // LOG_TYPE_*
	 *           ...extra fields (apiCall, type, element_id, etc.)
	 *         ],
	 *         ...
	 *       ],
	 *       data => array                      // Group-level metadata
	 *     ],
	 *     ...
	 *   ],
	 *   logUid => string
	 * ]
	 *
	 * Case B: Admin "Send to translation" request
	 * - Same structure as Case A
	 * - requestParams may include posts, strings, batch info
	 * - logs may include st-batch element_id expansion (string_ids_in_batch)
	 */

	/**
	 * Option name that enables/disables logging.
	 * @var string
	 */
	const IS_ENABLED_OPTION_NAME = 'wpml_tm_job_log_is_enabled';

	/**
	 * Log type: informational.
	 * @var int
	 */
	const LOG_TYPE_INFO  = 0;

	/**
	 * Log type: error.
	 * @var int
	 */
	const LOG_TYPE_ERROR = 1;

	/**
	 * Group ID for sending jobs. Used when we send content for translation in the /inc/translation-management/translation-management.class.php.
	 * @var int
	 */
	const GROUP_ID_SEND_JOBS = 0;

	/**
	 * Group ID for syncing jobs. Used when we sync jobs in the /classes/ATE/Sync/Process.php.
	 * @var int
	 */
	const GROUP_ID_SYNC_JOBS = 1;

	/**
	 * Group ID for downloading jobs. Used when we download jobs in the /classes/ATE/Download/Process.php.
	 * @var int
	 */
	const GROUP_ID_DOWNLOAD_JOBS = 2;

	/**
	 * Prevents calling from unit tests methods from $sitepress and $wpdb.
	 * @var bool
	 */
	private static $isInitialised = false;

	/**
	 * Whether current request has any error logs. When it is setup request will render having error state in the logs UI.
	 * @var bool
	 */
	private static $hasRequestAnyErrorLog = false;

	/**
	 * Cached flag whether logging is enabled. It is enabled by the button in the logging screen.
	 * That screen is accessible from the WPML support page.
	 *
	 * @var bool|null
	 */
	private static $isEnabled;

	/**
	 * Stores current request URL. If this value is null that means request was never initialized.
	 * We want to log only explicit places in the code and avoid cases when we created some logs from some child class
	 * that called addLog out of the scope when we explicitly initialised the request. Such calls can create many
	 * useless entries in the database which give no value for the inspection of those logs and could make option entry grow fast.
	 * @var string|null
	 */
	private static $requestUrl;

	/**
	 * Stores parsed request parameters from the current request.
	 * @var array
	 */
	private static $requestParams = [];

	/**
	 * Request datetime in ISO-8601 UTC format.
	 * @var string|null
	 */
	private static $requestDateTime;

	/**
	 * Logs grouped by logical groups. In Case A: REST / ATE sync/download request there can be 1 logical group.
	 *
	 * In Case B: Admin "Send to translation" request there can be multiple logical groups, 1 group per each send_jobs function call.
	 *
	 * This will allow to group logs into collapsible containers in the UI.
	 *
	 * @var array<int, array>
	 */
	private static $logsByGroup = [];

	/**
	 * Current opened group ID.
	 * @var int|null
	 */
	private static $groupId;

	/**
	 * Current opened group label.
	 * @var string|null
	 */
	private static $groupLabel;

	/**
	 * Logs of the current opened group.
	 * @var array<int, array>
	 */
	private static $groupLogs = [];

	/**
	 * Arbitrary data attached to current opened group.
	 * @var array
	 */
	private static $groupData = [];

	/**
	 * Object IDs already logged to prevent recursion in dataToArray function call.
	 * @var array<int>
	 */
	private static $alreadyAddedToLog = [];

	/**
	 * Extra data merged into each log entry. The caller should manually call removeExtraLog data to remove it.
	 * Otherwise it will be added to all next logs. It is needed to track some optional properties inside each log
	 * inside current group, for example current processed language during the step when we send content for translation.
	 * @var array<string, mixed>
	 */
	private static $extraLogData = [];

	/**
	 * Collected string batch IDs. We need to store them to select strings later and show string names and ids from each batch in UI.
	 * @var array<int>
	 */
	private static $stringBatchIds = [];

	/**
	 * @param int $groupId
	 * @return bool
	 */
	public static function isSendJobsLogsGroup( $groupId ) {
		return self::GROUP_ID_SEND_JOBS === (int) $groupId;
	}

	public static function init() {
		self::$isInitialised = true;
		add_action( 'shutdown', [ __CLASS__, 'shutdown' ], PHP_INT_MAX );
	}

	/**
	 * Checking in wp_options if logging was enabled from the UI by user.
	 * 
	 * @return bool
	 */
	public static function isEnabled() {
		if ( ! self::$isInitialised ) {
			return false;
		}

		if ( ! is_null( self::$isEnabled ) ) {
			return self::$isEnabled;
		}

		global $sitepress;
		if ( ! $sitepress ) {
			return false;
		}

		return self::$isEnabled = (bool) $sitepress->get_setting( self::IS_ENABLED_OPTION_NAME, false );
	}

	/**
	 * Checks whether logging is allowed for current request.
	 *
	 * @return bool
	 */
	private static function canLog() {
		return self::wasRequestInitialised() && self::isEnabled();
	}

	/**
	 * @param bool $isEnabled
	 */
	public static function setIsEnabled( $isEnabled ) {
		global $sitepress;
		$sitepress->set_setting( self::IS_ENABLED_OPTION_NAME, (bool) $isEnabled );
		$sitepress->save_settings();
	}

	/**
	 * This call should start the logging process and be called before starting any logging.
	 *
	 * @return void
	 */
	public static function maybeInitRequest() {
		if ( ! is_null( self::$requestDateTime ) ) {
			return;
		}

		self::$requestUrl      = $_SERVER['REQUEST_URI'] ?? null;
		self::$requestParams   = self::getUrlParams();
		self::$requestDateTime = gmdate('Y-m-d\TH:i:s\Z');
	}

	/**
	 * Check if request data was initialized.
	 *
	 * @return bool
	 */
	public static function wasRequestInitialised() {
		return is_string( self::$requestUrl );
	}

	/**
	 * Start a new logging group.
	 *
	 * @param int    $groupId
	 * @param string $groupLabel
	 * @param array  $groupData
	 */
	public static function createNewGroup( $groupId, $groupLabel = '', $groupData = [] ) {
		if ( ! self::canLog() ) {
			return;
		}

		self::$groupId    = $groupId;
		self::$groupLabel = $groupLabel;
		self::$groupLogs  = [];
		self::$groupData  = $groupData;
	}

	public static function finishCurrentGroup() {
		if ( ! self::canLog() ) {
			return;
		}

		self::$logsByGroup[] = [
			'groupId'       => self::$groupId,
			'label'         => self::$groupLabel,
			'logs'          => self::$groupLogs,
			'data'          => self::$groupData,
		];

		self::$groupLogs  = [];
		self::$groupId    = null;
		self::$groupLabel = null;
		self::$groupData  = [];
	}

	/**
	 * Add extra metadata which will be auto appended to all next logs.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @return void
	 */
	public static function addExtraLogData( $key, $value ) {
		if ( ! self::canLog() ) {
			return;
		}

		if (
			$key === 'element_id'
			&& isset( self::$extraLogData['type'] )
			&& self::$extraLogData['type'] === 'st-batch'
		) {
			if ( ! in_array( $value, self::$stringBatchIds, true ) ) {
				self::$stringBatchIds[] = $value;
			}
		}

		self::$extraLogData[ $key ] = $value;
	}

	/**
	 * Remove extra metadata key.
	 *
	 * @param string $key
	 */
	public static function removeExtraLogData( $key ) {
		if ( ! self::canLog() ) {
			return;
		}

		unset( self::$extraLogData[ $key ] );
	}

	/**
	 * Add a log entry to the current log group.
	 *
	 * @param string|int $id
	 * @param mixed      $data
	 * @param int        $logType
	 */
	public static function add( $id, $data = [], $logType = self::LOG_TYPE_INFO ) {
		if ( ! self::canLog() ) {
			return;
		}

		self::$alreadyAddedToLog = [];

		$logs = [
			'id'      => $id,
			'data'    => self::dataToArray( $data ),
			'trace'   => self::getTrace(),
			'logType' => $logType,
		];

		$logs = array_merge( $logs, self::$extraLogData );

		self::$groupLogs[] = $logs;
	}

	/**
	 * Check whether given log entry is an error.
	 *
	 * @return bool
	 */
	public static function isErrorLog( $log ) {
		return (int) $log['logType'] === self::LOG_TYPE_ERROR;
	}

	/**
	 * Add an error log entry. Error log entries will be displayed in special way in the UX.
	 *
	 * @param string|int $id
	 * @param mixed      $data
	 */
	public static function addError( $id, $data = [] ) {
		self::$hasRequestAnyErrorLog = true;
		self::add( $id, $data, self::LOG_TYPE_ERROR );
	}

	public static function getLogsCount() {
		return FsJobLogStorage::getLogsCount();
	}

	/**
	 * Get all stored logs.
	 *
	 * @return array
	 */
	public static function getLogs() {
		return FsJobLogStorage::getRequestLogs();
	}

	/**
	 * Clear all stored logs.
	 * 
	 * @return bool
	 */
	public static function clearLogs() {
		return FsJobLogStorage::clearAllLogs();
	}

	/**
	 * Shutdown handler – persists logs to DB.
	 */
	public static function shutdown() {
		if ( ! self::canLog() ) {
			return;
		}

		if ( count( self::$logsByGroup ) === 0 && count( self::$groupLogs ) === 0 ) {
			return;
		}

		// Group did not closed properly because of the error or exception.
		if ( count( self::$groupLogs ) > 0 ) {
			self::finishCurrentGroup();
		}

		global $wpdb;

		$requestParams = self::$requestParams;
		if ( isset( $requestParams['posts'] ) && is_array( $requestParams['posts'] ) ) {
			for ( $i = 0; $i < count( $requestParams['posts'] ); $i++ ) {
				$postId = $requestParams['posts'][ $i ];
				$post   = get_post( $postId );
				if ( is_object( $post ) ) {
					$requestParams['posts'][ $i ] = $requestParams['posts'][ $i ] . ' (post title=' . $post->post_name . ')';
				}
			}
		}

		if ( isset( $requestParams['strings'] ) && is_array( $requestParams['strings'] ) ) {
			$stringIds = array_map( 'intval', $requestParams['strings'] );
			if ( $stringIds ) {
				$placeholders = implode( ',', array_fill( 0, count( $stringIds ), '%d' ) );
				$sql          = "
					SELECT id, value, context, gettext_context
					FROM {$wpdb->prefix}icl_strings
					WHERE id IN ($placeholders)
				";

				$results = $wpdb->get_results( $wpdb->prepare( $sql, $stringIds ), OBJECT_K );

				foreach ( $requestParams['strings'] as $i => $stringId ) {
					$id = (int) $stringId;
					if ( isset( $results[ $id ] ) ) {
						$value   = $results[ $id ]->value;
						$domain  = $results[ $id ]->context;
						$context = $results[ $id ]->gettext_context;

						$requestParams['strings'][ $i ] = $id . ' (string value=`' . $value . '`, context=`' . $context . '`, domain=`' . $domain . '`)';
					}
				}
			}
		}

		if ( count( self::$stringBatchIds ) > 0 ) {
			$stringBatchIds = array_map( 'intval', self::$stringBatchIds );
			if ( $stringBatchIds ) {
				$placeholders = implode( ',', array_fill( 0, count( $stringBatchIds ), '%d' ) );
				$sql          = "
					SELECT *
					FROM {$wpdb->prefix}icl_string_batches
					WHERE batch_id IN ($placeholders)
				";

				$results            = $wpdb->get_results( $wpdb->prepare( $sql, $stringBatchIds ), ARRAY_A );
				$stringIdsByBatchId = [];

				foreach ( $results as $result ) {
					$batchId  = (int) $result['batch_id'];
					$stringId = (int) $result['string_id'];

					if ( ! isset( $stringIdsByBatchId[ $batchId ] ) ) {
						$stringIdsByBatchId[ $batchId ] = [];
					}

					$stringIdsByBatchId[ $batchId ][] = $stringId;
				}

				// We need this info only for first log within same element_id group sequence.
				$alreadyAddedBatches = [];

				foreach ( self::$logsByGroup as &$groupLogs ) {
					foreach ( $groupLogs['logs'] as &$log ) {
						if ( ! isset( $log['type'] ) || $log['type'] !== 'st-batch' || ! isset( $log['element_id'] ) ) {
							unset( $log );
							continue;
						}

						$batchId   = (int) $log['element_id'];
						$stringIds = $stringIdsByBatchId[ $batchId ] ?? null;

						if ( in_array( $batchId, $alreadyAddedBatches ) || ! is_array( $stringIds ) ) {
							unset( $log );
							continue;
						}

						$log['string_ids_in_batch'] = $stringIds;
						unset( $log );
						$alreadyAddedBatches[] = $batchId;
					}
					unset( $groupLogs );
				}
			}
		}

		$log = [
			'requestUrl'      => self::$requestUrl,
			'requestParams'   => $requestParams,
			'requestDateTime' => self::$requestDateTime,
			'hasErrorLogs'    => self::$hasRequestAnyErrorLog,
			'logsByGroup'     => self::$logsByGroup,
			'logUid'          => uniqid(),
		];

		FsJobLogStorage::writeRequestLog( $log );
	}

	/**
	 * @param object $object
	 *
	 * @return string
	 */
	private static function getObjectId( $object ) {
		return (string) spl_object_hash( $object );
	}

	const MAX_DEPTH = 10;
	const MAX_STRING_LENGTH = 1000;
	const MAX_ARRAY_ITEMS = 1000;

	/**
	 * Normalize log data to array-safe format.
	 *
	 * @param mixed $data
	 * @param int   $depth
	 *
	 * @return array
	 */
	private static function dataToArray( $data, $depth = 0 ) {
		if ( $depth > self::MAX_DEPTH ) {
			return '[DEPTH_LIMIT]';
		}

		if ( is_callable( $data ) ) {
			return 'callable';
		}
		if ( is_resource( $data ) ) {
			return 'resource';
		}
		if ( is_string( $data ) && strlen( $data ) > self::MAX_STRING_LENGTH ) {
			return substr( $data, 0, self::MAX_STRING_LENGTH ) . '…[TRUNCATED]';
		}

		if ( is_object( $data ) ) {
			$id = self::getObjectId( $data );
			if ( in_array( $id, self::$alreadyAddedToLog ) ) {
				return '[RECURSION]';
			}
			self::$alreadyAddedToLog[] = $id;

			$data = get_object_vars( $data );
		}

		$count = 0;
		if ( is_array( $data ) ) {
			$result = [];

			foreach ( $data as $key => $value ) {
				if ( ++$count > self::MAX_ARRAY_ITEMS ) {
					$result[ $key ] = '[ARRAY_TRUNCATED]';
					break;
				}

				if ( $key === 'signedUrl' && is_string( $value ) && strpos( $value, 'signature=' ) !== false ) {
					$result[ $key ] = preg_replace(
						'/(?:^|[?&])(signature|token|shared_key)=([^&]+)/i',
						'$1=[REMOVED]',
						$value
					);
					continue;
				}

				$result[ $key ] = self::dataToArray( $value, $depth + 1 );
			}

			return $result;
		}

		return $data;
	}

	/**
	 * @param  string $url
	 * @return string
	 */
	private static function sanitizeSignedUrlWithRegex( $url ) {
		$res = preg_replace(
			'/(?:^|[?&])(signature|token|shared_key)=([^&]+)/',
			'$1=[REMOVED]',
			$url
		);
		return is_string( $res ) ? $res : '[EMPTY]';
	}

	/**
	 * Parse JSON request body into array.
	 * 
	 * @return array
	 */
	private static function getUrlParams() {
		$body = file_get_contents('php://input');

		if ($body === false || $body === '') {
			return [];
		}

		$params = json_decode( $body, true );

		return is_array($params) ? $params : [];
	}

	/**
	 * Get simplified backtrace for logging.
	 *
	 * @return array<string>
	 */
	private static function getTrace() {
		$debug_backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		$traces          = [];

		foreach ( $debug_backtrace as $trace ) {
			$trace = [
				'file' => $trace['file'] ?? '',
				'line' => $trace['line'] ?? '',
				'class' => $trace['class'] ?? '',
				'type' => $trace['type'] ?? '',
				'function' => $trace['function'] ?? '',
			];

			$traces[] = $trace['file'] . ':' . $trace['line'] . ' ' . $trace['class'] . '/' . $trace['function'];
		}

		return $traces;
	}
}