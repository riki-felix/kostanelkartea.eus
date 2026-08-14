<?php

namespace Kostan\Komunikazioa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class User_Importer {
	const IMPORT_ROLE        = 'socios';
	const MAX_FILE_BYTES     = 2097152;
	const IMPORT_BATCH_SIZE  = 10;
	const EMAIL_COLUMN       = 'email';
	const LOGIN_COLUMN       = 'user_login';

	/**
	 * Parse a CSV upload into normalized rows.
	 *
	 * @param string $file_path Absolute path to CSV file.
	 * @return array|\WP_Error
	 */
	public static function parse_csv_file( $file_path ) {
		if ( ! is_readable( $file_path ) ) {
			return new \WP_Error( 'unreadable', Plugin::admin_label( 'No se pudo leer el archivo CSV.', 'Ezin izan da CSV fitxategia irakurri.' ) );
		}

		$handle = fopen( $file_path, 'rb' );
		if ( false === $handle ) {
			return new \WP_Error( 'open_failed', Plugin::admin_label( 'No se pudo abrir el archivo CSV.', 'Ezin izan da CSV fitxategia ireki.' ) );
		}

		$rows        = array();
		$line_number = 0;
		$headers     = null;

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			++$line_number;

			if ( 1 === $line_number && isset( $data[0] ) ) {
				$data[0] = self::strip_bom( (string) $data[0] );
			}

			if ( self::is_empty_row( $data ) ) {
				continue;
			}

			if ( null === $headers ) {
				$mapped = self::map_header_row( $data );
				if ( $mapped ) {
					$headers = $mapped;
					continue;
				}

				$headers = array(
					self::EMAIL_COLUMN => 0,
					self::LOGIN_COLUMN => 1,
				);
			}

			$email = isset( $data[ $headers[ self::EMAIL_COLUMN ] ] ) ? trim( (string) $data[ $headers[ self::EMAIL_COLUMN ] ] ) : '';
			$login = isset( $data[ $headers[ self::LOGIN_COLUMN ] ] ) ? trim( (string) $data[ $headers[ self::LOGIN_COLUMN ] ] ) : '';

			$rows[] = array(
				'line'  => $line_number,
				'email' => sanitize_email( $email ),
				'login' => sanitize_user( $login, true ),
			);
		}

		fclose( $handle );

		if ( empty( $rows ) ) {
			return new \WP_Error( 'empty_csv', Plugin::admin_label( 'El CSV no contiene filas validas.', 'CSVak ez du baliozko errenkadarik.' ) );
		}

		return $rows;
	}

	/**
	 * Build an empty import report.
	 *
	 * @param string $mode Import mode.
	 * @return array
	 */
	public static function empty_report( $mode ) {
		return array(
			'mode'        => sanitize_key( (string) $mode ),
			'created'     => array(),
			'skipped'     => array(),
			'errors'      => array(),
			'mailed'      => array(),
			'mail_failed' => array(),
		);
	}

	/**
	 * Merge two import reports.
	 *
	 * @param array $base     Base report.
	 * @param array $addition Report to append.
	 * @return array
	 */
	public static function merge_reports( array $base, array $addition ) {
		foreach ( array( 'created', 'skipped', 'errors', 'mailed', 'mail_failed' ) as $section ) {
			if ( empty( $addition[ $section ] ) || ! is_array( $addition[ $section ] ) ) {
				continue;
			}

			if ( empty( $base[ $section ] ) || ! is_array( $base[ $section ] ) ) {
				$base[ $section ] = array();
			}

			$base[ $section ] = array_merge( $base[ $section ], $addition[ $section ] );
		}

		return $base;
	}

	/**
	 * Create a background import job for batched processing.
	 *
	 * @param array $rows           Parsed CSV rows.
	 * @param int   $admin_user_id  Admin user that started the import.
	 * @return array
	 */
	public static function create_import_job( array $rows, $admin_user_id ) {
		return array(
			'id'         => 'import_' . wp_generate_password( 12, false, false ),
			'status'     => 'running',
			'started_by' => (int) $admin_user_id,
			'rows'       => array_values( $rows ),
			'cursor'     => 0,
			'total'      => count( $rows ),
			'report'     => self::empty_report( 'full' ),
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);
	}

	/**
	 * Process the next batch of rows for a background import job.
	 *
	 * @param array    $job         Import job (passed by reference).
	 * @param int|null $batch_size  Rows per batch.
	 * @return int Number of rows processed in this batch.
	 */
	public static function process_import_batch( array &$job, $batch_size = null ) {
		if ( empty( $job['status'] ) || 'running' !== $job['status'] ) {
			return 0;
		}

		if ( null === $batch_size ) {
			$batch_size = (int) apply_filters( 'komunikazioa_import_batch_size', self::IMPORT_BATCH_SIZE );
		}

		$batch_size = max( 1, (int) $batch_size );
		$delay_us   = (int) apply_filters( 'komunikazioa_import_batch_delay_us', 100000 );

		if ( ! get_role( self::IMPORT_ROLE ) ) {
			$job['status'] = 'failed';
			$job['report'] = self::merge_reports(
				isset( $job['report'] ) && is_array( $job['report'] ) ? $job['report'] : self::empty_report( 'full' ),
				array(
					'mode'    => 'full',
					'errors'  => array(
						array(
							'line'    => 0,
							'email'   => '',
							'login'   => '',
							'message' => Plugin::admin_label(
								'El rol "socios" no existe en WordPress. Crealo antes de importar.',
								'"socios" rola ez dago WordPressen. Sortu ezazu inportatu aurretik.'
							),
						),
					),
					'created'     => array(),
					'skipped'     => array(),
					'mailed'      => array(),
					'mail_failed' => array(),
				)
			);
			$job['updated_at'] = current_time( 'mysql' );
			return 0;
		}

		$rows       = isset( $job['rows'] ) && is_array( $job['rows'] ) ? $job['rows'] : array();
		$row_count  = count( $rows );
		$cursor     = isset( $job['cursor'] ) ? (int) $job['cursor'] : 0;
		$processed  = 0;
		$report     = isset( $job['report'] ) && is_array( $job['report'] ) ? $job['report'] : self::empty_report( 'full' );

		while ( $cursor < $row_count && $processed < $batch_size ) {
			$row_report = self::process_single_row( $rows[ $cursor ], 'full' );
			$report     = self::merge_reports( $report, $row_report );
			++$cursor;
			++$processed;

			if ( $cursor < $row_count && $processed < $batch_size && $delay_us > 0 ) {
				usleep( $delay_us );
			}
		}

		$job['cursor']     = $cursor;
		$job['report']     = $report;
		$job['updated_at'] = current_time( 'mysql' );

		if ( $cursor >= $row_count ) {
			$job['status'] = 'completed';
		}

		return $processed;
	}

	/**
	 * Summarize report counts for progress UI.
	 *
	 * @param array $report Import report.
	 * @return array
	 */
	public static function summarize_report( array $report ) {
		return array(
			'created'     => count( isset( $report['created'] ) ? $report['created'] : array() ),
			'skipped'     => count( isset( $report['skipped'] ) ? $report['skipped'] : array() ),
			'errors'      => count( isset( $report['errors'] ) ? $report['errors'] : array() ),
			'mailed'      => count( isset( $report['mailed'] ) ? $report['mailed'] : array() ),
			'mail_failed' => count( isset( $report['mail_failed'] ) ? $report['mail_failed'] : array() ),
		);
	}

	/**
	 * Run import for simulate or test modes (synchronous).
	 *
	 * @param array  $rows Parsed rows.
	 * @param string $mode simulate|test|full.
	 * @return array
	 */
	public static function process_rows( array $rows, $mode ) {
		$mode = sanitize_key( (string) $mode );
		if ( ! in_array( $mode, array( 'simulate', 'test', 'full' ), true ) ) {
			$mode = 'simulate';
		}

		$report = self::empty_report( $mode );

		if ( 'simulate' !== $mode && ! get_role( self::IMPORT_ROLE ) ) {
			$report['errors'][] = array(
				'line'    => 0,
				'email'   => '',
				'login'   => '',
				'message' => Plugin::admin_label(
					'El rol "socios" no existe en WordPress. Crealo antes de importar.',
					'"socios" rola ez dago WordPressen. Sortu ezazu inportatu aurretik.'
				),
			);
			return $report;
		}

		$processed_valid = 0;

		foreach ( $rows as $row ) {
			$row_report = self::process_single_row( $row, $mode );
			$report     = self::merge_reports( $report, $row_report );

			if ( 'simulate' === $mode ) {
				continue;
			}

			if ( ! empty( $row_report['created'] ) || ! empty( $row_report['mail_failed'] ) ) {
				++$processed_valid;
			}

			if ( 'test' === $mode && $processed_valid > 0 ) {
				break;
			}
		}

		return $report;
	}

	/**
	 * Process one CSV row.
	 *
	 * @param array  $row  Parsed row.
	 * @param string $mode simulate|test|full.
	 * @return array
	 */
	public static function process_single_row( array $row, $mode ) {
		$report = self::empty_report( $mode );

		$validation = self::validate_row( $row );
		if ( is_wp_error( $validation ) ) {
			$report['errors'][] = array(
				'line'    => (int) $row['line'],
				'email'   => (string) $row['email'],
				'login'   => (string) $row['login'],
				'message' => $validation->get_error_message(),
			);
			return $report;
		}

		if ( email_exists( $row['email'] ) || username_exists( $row['login'] ) ) {
			$existing_user = get_user_by( 'email', $row['email'] );
			if ( ! ( $existing_user instanceof \WP_User ) ) {
				$existing_user = get_user_by( 'login', $row['login'] );
			}

			$skipped_row = array(
				'line'    => (int) $row['line'],
				'email'   => (string) $row['email'],
				'login'   => (string) $row['login'],
				'message' => Plugin::admin_label( 'Usuario o email ya existente.', 'Erabiltzailea edo emaila dagoeneko existitzen da.' ),
			);

			if ( $existing_user instanceof \WP_User ) {
				$skipped_row['user_id'] = (int) $existing_user->ID;
				if ( 'simulate' !== $mode ) {
					Plugin::add_to_import_roster( $existing_user );
				}
			}

			$report['skipped'][] = $skipped_row;
			return $report;
		}

		if ( 'simulate' === $mode ) {
			$report['created'][] = array(
				'line'    => (int) $row['line'],
				'email'   => (string) $row['email'],
				'login'   => (string) $row['login'],
				'message' => Plugin::admin_label( 'Fila valida (simulacion).', 'Errenkada baliozkoa (simulazioa).' ),
			);
			return $report;
		}

		$user_id = self::create_user( $row['email'], $row['login'] );
		if ( is_wp_error( $user_id ) ) {
			$report['errors'][] = array(
				'line'    => (int) $row['line'],
				'email'   => (string) $row['email'],
				'login'   => (string) $row['login'],
				'message' => $user_id->get_error_message(),
			);
			return $report;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! ( $user instanceof \WP_User ) ) {
			$report['errors'][] = array(
				'line'    => (int) $row['line'],
				'email'   => (string) $row['email'],
				'login'   => (string) $row['login'],
				'message' => Plugin::admin_label( 'No se pudo cargar el usuario creado.', 'Ezin izan da sortutako erabiltzailea kargatu.' ),
			);
			return $report;
		}

		$report['created'][] = array(
			'line'    => (int) $row['line'],
			'email'   => (string) $row['email'],
			'login'   => (string) $row['login'],
			'user_id' => (int) $user->ID,
			'message' => Plugin::admin_label( 'Usuario creado.', 'Erabiltzailea sortuta.' ),
		);

		Plugin::add_to_import_roster( $user );

		$sent = Plugin::send_user_onboarding_mail( $user );
		if ( $sent ) {
			$report['mailed'][] = array(
				'line'    => (int) $row['line'],
				'email'   => (string) $row['email'],
				'login'   => (string) $row['login'],
				'user_id' => (int) $user->ID,
				'message' => Plugin::admin_label( 'Correo de bienvenida enviado.', 'Ongietorri mezua bidalita.' ),
			);
		} else {
			$report['mail_failed'][] = array(
				'line'    => (int) $row['line'],
				'email'   => (string) $row['email'],
				'login'   => (string) $row['login'],
				'user_id' => (int) $user->ID,
				'message' => Plugin::get_last_mail_error_message(),
			);
		}

		return $report;
	}

	/**
	 * Validate a parsed row.
	 *
	 * @param array $row Parsed row.
	 * @return true|\WP_Error
	 */
	private static function validate_row( array $row ) {
		if ( empty( $row['email'] ) || ! is_email( $row['email'] ) ) {
			return new \WP_Error( 'invalid_email', Plugin::admin_label( 'Email no valido.', 'Email baliogabea.' ) );
		}

		if ( empty( $row['login'] ) ) {
			return new \WP_Error( 'invalid_login', Plugin::admin_label( 'Nombre de usuario vacio.', 'Erabiltzaile-izena hutsik.' ) );
		}

		$login_check = validate_username( $row['login'] );
		if ( is_wp_error( $login_check ) ) {
			return new \WP_Error( 'invalid_login', $login_check->get_error_message() );
		}

		return true;
	}

	/**
	 * Create a socios user without core notifications.
	 *
	 * @param string $email User email.
	 * @param string $login User login.
	 * @return int|\WP_Error
	 */
	private static function create_user( $email, $login ) {
		add_filter( 'wp_send_new_user_notification_to_admin', '__return_false' );
		add_filter( 'wp_send_new_user_notification_to_user', '__return_false' );

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'role'         => self::IMPORT_ROLE,
				'display_name' => $login,
			)
		);

		remove_filter( 'wp_send_new_user_notification_to_admin', '__return_false' );
		remove_filter( 'wp_send_new_user_notification_to_user', '__return_false' );

		if ( ! is_wp_error( $user_id ) ) {
			update_user_meta( (int) $user_id, 'show_admin_bar_front', 'false' );
		}

		return $user_id;
	}

	/**
	 * Map a header row to column indexes.
	 *
	 * @param array $data CSV row.
	 * @return array|null
	 */
	private static function map_header_row( array $data ) {
		$normalized = array();
		foreach ( $data as $index => $value ) {
			$key = strtolower( trim( self::strip_bom( (string) $value ) ) );
			$normalized[ $key ] = (int) $index;
		}

		$email_keys = array( 'email', 'correo', 'e-mail', 'user_email' );
		$login_keys = array( 'user_login', 'usuario', 'username', 'login', 'user' );

		$email_index = null;
		$login_index = null;

		foreach ( $email_keys as $key ) {
			if ( isset( $normalized[ $key ] ) ) {
				$email_index = $normalized[ $key ];
				break;
			}
		}

		foreach ( $login_keys as $key ) {
			if ( isset( $normalized[ $key ] ) ) {
				$login_index = $normalized[ $key ];
				break;
			}
		}

		if ( null === $email_index || null === $login_index ) {
			return null;
		}

		return array(
			self::EMAIL_COLUMN => $email_index,
			self::LOGIN_COLUMN => $login_index,
		);
	}

	/**
	 * Strip UTF-8 BOM from a string.
	 *
	 * @param string $value Input value.
	 * @return string
	 */
	private static function strip_bom( $value ) {
		if ( str_starts_with( $value, "\xEF\xBB\xBF" ) ) {
			return substr( $value, 3 );
		}

		return $value;
	}

	/**
	 * Check whether a CSV row is empty.
	 *
	 * @param array $data CSV row.
	 * @return bool
	 */
	private static function is_empty_row( array $data ) {
		foreach ( $data as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}
}
