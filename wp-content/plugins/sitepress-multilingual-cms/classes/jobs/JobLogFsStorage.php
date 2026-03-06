<?php

namespace WPML\TM\Jobs;

class FsJobLogStorage {

	/**
	 * Max number of stored request logs.
	 * Oldest logs are removed first.
	 */
	const MAX_STORED_REQUESTS_COUNT = 50;

	/**
	 * Ensure target directory exists.
	 *
	 * @return void
	 */
	private static function ensureQueueDir() {
		$wpmlDir = self::getWpmlDir();

		if ( ! file_exists( $wpmlDir ) ) {
			mkdir( $wpmlDir, 0777, true );
		}

		$queueDir = self::getQueueDir();

		if ( ! file_exists( $queueDir ) ) {
			mkdir( $queueDir, 0777, true );
		}
	}

	/**
	 * @return string
	 */
	private static function getWpmlDir() {
		return WP_LANG_DIR . '/wpml/';
	}

	/**
	 * Queue directory (site-aware for multisite).
	 *
	 * @return string
	 */
	private static function getQueueDir() {
		$subdir = '';

		if ( is_multisite() ) {
			$subdir = get_current_blog_id() . '/';
		}

		return self::getWpmlDir() . 'joblog/' . $subdir;
	}

	/**
	 * Full filepath for a given filename.
	 *
	 * @param string $filename
	 * @return string
	 */
	private static function getFilepath( $filename ) {
		return self::getQueueDir() . $filename;
	}

	/**
	 * Write a single request log to filesystem..
	 *
	 * @param array $requestLog
	 */
	public static function writeRequestLog( array $requestLog ) {
		self::ensureQueueDir();

		$filename = self::generateFilename();
		$filepath = self::getFilepath( $filename );

		$json = json_encode( $requestLog, JSON_PRETTY_PRINT );

		if ( ! is_string( $json ) ) {
			return;
		}

		$filesystem = new \WP_Filesystem_Direct( null );
		$chmod      = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;
		$filesystem->put_contents( $filepath, $json, $chmod );

		self::cleanupOldLogs();
	}

	/**
	 * Read all stored request logs.
	 *
	 * @return array<int, array>
	 */
	public static function getRequestLogs() {
		$dir = self::getQueueDir();

		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$files = glob( $dir . '*.json' );

		if ( ! is_array( $files ) ) {
			return [];
		}

		// Newest first
		rsort( $files );

		$logs = [];

		foreach ( $files as $file ) {
			$content = file_get_contents( $file );
			if ( ! is_string( $content ) ) {
				continue;
			}

			$decoded = json_decode( $content, true );
			if ( is_array( $decoded ) ) {
				$logs[] = $decoded;
			}
		}

		return $logs;
	}

	/**
	 * Remove old log files beyond the max limit.
	 *
	 * @return void
	 */
	private static function cleanupOldLogs() {
		$dir = self::getQueueDir();

		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = glob( $dir . '*.json' );

		if ( ! is_array( $files ) || count( $files ) <= self::MAX_STORED_REQUESTS_COUNT ) {
			return;
		}

		// Oldest first
		sort( $files );

		$filesToDelete = array_slice(
			$files,
			0,
			count( $files ) - self::MAX_STORED_REQUESTS_COUNT
		);

		foreach ( $filesToDelete as $file ) {
			unlink( $file );
		}
	}

	/**
	 * Remove all stored request log files.
	 *
	 * @return bool
	 */
	public static function clearAllLogs() {
		$dir = self::getQueueDir();

		if ( ! is_dir( $dir ) ) {
			return true;
		}

		$files = glob( $dir . '*.json' );

		if ( ! is_array( $files ) ) {
			return false;
		}

		foreach ( $files as $file ) {
			unlink( $file );
		}

		return true;
	}

	/**
	 * Get number of stored request logs.
	 *
	 * @return int
	 */
	public static function getLogsCount() {
		$dir = self::getQueueDir();

		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$files = glob( $dir . '*.json' );

		if ( ! is_array( $files ) ) {
			return 0;
		}

		return count( $files );
	}

	/**
	 * Generate unique filename for request log.
	 *
	 * @return string
	 */
	private static function generateFilename() {
		return gmdate( 'Y-m-d\TH-i-s' ) . '_' . uniqid() . '.json';
	}
}
