<?php

namespace Kostan\Komunikazioa;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	const CPT = 'komunikazioa_mail';
	const LEGACY_CPT = 'komunikazioa_campaign';
	const LEADS_TABLE = 'komunikazioa_leads';
	const LOGS_TABLE = 'komunikazioa_mail_logs';
	const CRON_HOOK = 'komunikazioa_process_campaigns';
	const SETTINGS_PAGE = 'komunikazioa-settings';
	const SETTINGS_POST_ID = 'komunikazioa_settings';

	/** @var string|null */
	private static $mail_error = null;

	/**
	 * Boot the plugin.
	 */
	public static function init() {
		self::ensure_administrator_capabilities();

		add_action( 'init', array( __CLASS__, 'register_campaign_cpt' ) );
		add_action( 'init', array( __CLASS__, 'maybe_upgrade_tables' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_cpt_urls' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'init', array( __CLASS__, 'register_shortcode_compat' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_public_assets' ) );
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_due_campaigns' ) );
		add_action( 'admin_post_komunikazioa_send_test_email', array( __CLASS__, 'handle_test_email_submit' ) );
		add_action( 'admin_post_komunikazioa_submit_lead', array( __CLASS__, 'handle_lead_submit' ) );
		add_action( 'admin_post_nopriv_komunikazioa_submit_lead', array( __CLASS__, 'handle_lead_submit' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'capture_mail_error' ) );
		add_action( 'acf/init', array( __CLASS__, 'register_acf_integration' ) );
		add_action( 'acf/save_post', array( __CLASS__, 'sync_campaign_status_from_acf' ), 20 );
		add_action( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'filter_campaign_columns' ) );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'render_campaign_column' ), 10, 2 );
	}

	/**
	 * Create DB tables and schedule the cron job.
	 */
	public static function activate() {
		self::ensure_administrator_capabilities();
		self::create_tables();
		self::maybe_upgrade_tables();

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'komunikazioa_five_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Clear the scheduled job.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Add a 5 minute cron interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function register_cron_schedule( $schedules ) {
		$schedules['komunikazioa_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( '5 minuturo', 'komunikazioa' ),
		);

		return $schedules;
	}

	/**
	 * Create the custom tables used by the plugin.
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$leads_table     = $wpdb->prefix . self::LEADS_TABLE;
		$logs_table      = $wpdb->prefix . self::LOGS_TABLE;

		$sql_leads = "CREATE TABLE {$leads_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_type varchar(40) NOT NULL,
			full_name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(60) NOT NULL DEFAULT '',
			city varchar(190) NOT NULL DEFAULT '',
			birth_year varchar(10) NOT NULL DEFAULT '',
			terms_accepted tinyint(1) NOT NULL DEFAULT 0,
			source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ip_address varchar(100) NOT NULL DEFAULT '',
			user_agent text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$logs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			recipient_email varchar(190) NOT NULL DEFAULT '',
			recipient_name varchar(190) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'sent',
			error_message text NULL,
			error_code varchar(190) NOT NULL DEFAULT '',
			attempted_at datetime NOT NULL,
			sent_at datetime NULL,
			PRIMARY KEY  (id),
			KEY campaign_id (campaign_id),
			KEY status (status),
			KEY recipient_email (recipient_email)
		) {$charset_collate};";

		dbDelta( $sql_leads );
		dbDelta( $sql_logs );
	}

	/**
	 * Apply lightweight schema upgrades on existing installs.
	 */
	private static function maybe_upgrade_tables() {
		global $wpdb;

		$table = $wpdb->prefix . self::LEADS_TABLE;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$city = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'city' ) );

		if ( ! $city ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN city varchar(190) NOT NULL DEFAULT '' AFTER phone" );
		}
	}

	/**
	 * Enqueue frontend styles for public lead forms.
	 */
	public static function enqueue_public_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'komunikazioa-forms',
			KOMUNIKAZIOA_URL . '/assets/css/forms.css',
			array(),
			KOMUNIKAZIOA_VERSION
		);
	}

	/**
	 * Register the campaigns CPT.
	 */
	public static function register_campaign_cpt() {
		$labels = array(
			'name'               => self::admin_label( 'Comunicaciones', 'Komunikazioa' ),
			'singular_name'      => self::admin_label( 'Comunicación', 'Komunikazio mezua' ),
			'add_new'            => self::admin_label( 'Nueva comunicación', 'Mezu berria' ),
			'add_new_item'       => self::admin_label( 'Añadir nueva comunicación', 'Mezu berria gehitu' ),
			'edit_item'          => self::admin_label( 'Editar comunicación', 'Mezua editatu' ),
			'new_item'           => self::admin_label( 'Nueva comunicación', 'Mezu berria' ),
			'view_item'          => self::admin_label( 'Ver comunicación', 'Mezua ikusi' ),
			'search_items'       => self::admin_label( 'Buscar comunicaciones', 'Mezuak bilatu' ),
			'not_found'          => self::admin_label( 'No se han encontrado comunicaciones.', 'Ez da mezurik aurkitu' ),
			'not_found_in_trash' => self::admin_label( 'No se han encontrado comunicaciones en la papelera.', 'Ez da mezurik aurkitu zaborrontzian' ),
			'menu_name'          => self::admin_label( 'Komunikazioa', 'Komunikazioa' ),
		);

		register_post_type(
			self::CPT,
			array(
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_rest'       => false,
				'show_in_menu'       => false,
				'menu_position'      => 25,
				'menu_icon'          => 'dashicons-email-alt2',
				'supports'           => array( 'title' ),
				'has_archive'        => false,
				'rewrite'            => false,
				'query_var'          => false,
				'capability_type'    => array( 'komunikazioa_mail', 'komunikazioa_mails' ),
				'capabilities'       => array(
					'edit_post'              => 'edit_komunikazioa_mail',
					'read_post'              => 'read_komunikazioa_mail',
					'delete_post'            => 'delete_komunikazioa_mail',
					'edit_posts'             => 'edit_komunikazioa_mails',
					'edit_others_posts'      => 'edit_others_komunikazioa_mails',
					'publish_posts'          => 'publish_komunikazioa_mails',
					'read_private_posts'     => 'read_private_komunikazioa_mails',
					'delete_posts'           => 'delete_komunikazioa_mails',
					'delete_private_posts'   => 'delete_private_komunikazioa_mails',
					'delete_published_posts' => 'delete_published_komunikazioa_mails',
					'delete_others_posts'    => 'delete_others_komunikazioa_mails',
					'edit_private_posts'     => 'edit_private_komunikazioa_mails',
					'edit_published_posts'   => 'edit_published_komunikazioa_mails',
					'create_posts'           => 'edit_komunikazioa_mails',
				),
				'map_meta_cap'       => true,
			)
		);
	}

	/**
	 * Ensure administrators have access to the Komunikazioa campaign CPT.
	 *
	 * @return void
	 */
	private static function ensure_administrator_capabilities() {
		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		$caps = array(
			'edit_komunikazioa_mail',
			'read_komunikazioa_mail',
			'delete_komunikazioa_mail',
			'edit_komunikazioa_mails',
			'edit_others_komunikazioa_mails',
			'publish_komunikazioa_mails',
			'read_private_komunikazioa_mails',
			'delete_komunikazioa_mails',
			'delete_private_komunikazioa_mails',
			'delete_published_komunikazioa_mails',
			'delete_others_komunikazioa_mails',
			'edit_private_komunikazioa_mails',
			'edit_published_komunikazioa_mails',
		);

		foreach ( $caps as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Redirect old CPT URLs to the current CPT key.
	 */
	public static function redirect_legacy_cpt_urls() {
		if ( ! is_admin() ) {
			return;
		}

		if ( empty( $_GET['post_type'] ) ) {
			return;
		}

		$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
		if ( self::LEGACY_CPT !== $post_type ) {
			return;
		}

		$target = add_query_arg( 'post_type', self::CPT, admin_url( 'edit.php' ) );
		if ( false !== strpos( (string) $_SERVER['REQUEST_URI'], 'post-new.php' ) ) {
			$target = add_query_arg( 'post_type', self::CPT, admin_url( 'post-new.php' ) );
		}

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Register the admin shell page and leads page.
	 */
	public static function register_admin_menu() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_menu_page(
			self::admin_label( 'Komunikazioa', 'Komunikazioa' ),
			self::admin_label( 'Komunikazioa', 'Komunikazioa' ),
			'manage_options',
			'komunikazioa',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-email-alt2',
			25
		);

		remove_submenu_page( 'komunikazioa', 'komunikazioa' );

		add_submenu_page(
			'komunikazioa',
			self::admin_label( 'Resumen', 'Laburpena' ),
			self::admin_label( 'Resumen', 'Laburpena' ),
			'manage_options',
			'komunikazioa',
			array( __CLASS__, 'render_dashboard_page' )
		);

		add_submenu_page(
			'komunikazioa',
			self::admin_label( 'Mensajes', 'Mezuak' ),
			self::admin_label( 'Mensajes', 'Mezuak' ),
			'manage_options',
			'edit.php?post_type=' . self::CPT
		);

		add_submenu_page(
			'komunikazioa',
			self::admin_label( 'Interesados', 'Interesdunak' ),
			self::admin_label( 'Interesados', 'Interesdunak' ),
			'manage_options',
			'komunikazioa-leads',
			array( __CLASS__, 'render_leads_page' )
		);
	}

	/**
	 * Register ACF options page, blocks and field groups.
	 */
	public static function register_acf_integration() {
		if ( function_exists( 'acf_add_options_page' ) ) {
			acf_add_options_page(
				array(
					'page_title' => self::admin_label( 'Ajustes de Komunikazioa', 'Komunikazioa Ezarpenak' ),
					'menu_title' => self::admin_label( 'Ajustes', 'Ezarpenak' ),
					'menu_slug'  => self::SETTINGS_PAGE,
					'parent_slug'=> 'komunikazioa',
					'capability' => 'manage_options',
					'post_id'    => self::SETTINGS_POST_ID,
					'autoload'   => false,
				)
			);
		}

		if ( function_exists( 'acf_add_local_field_group' ) ) {
			self::register_settings_fields();
			self::register_campaign_fields();
		}

		if ( function_exists( 'acf_register_block_type' ) ) {
			acf_register_block_type(
				array(
					'name'            => 'interesdunak-simple',
					'title'           => self::admin_label( 'Interesados - Simple', 'Interesdunak - Simple' ),
					'description'     => self::admin_label( 'Formulario simple para personas interesadas.', 'Pertsona interesdunentzako formulario sinplea.' ),
					'render_callback' => array( __CLASS__, 'render_simple_form_block' ),
					'category'        => 'widgets',
					'icon'            => 'email-alt',
					'keywords'        => array( 'leads', 'interesdunak', 'email' ),
					'supports'        => array(
						'align'    => array( 'wide', 'full' ),
						'multiple' => false,
					),
				)
			);

			acf_register_block_type(
				array(
					'name'            => 'interesdunak-full',
					'title'           => self::admin_label( 'Interesados - Completo', 'Interesdunak - Completo' ),
					'description'     => self::admin_label( 'Formulario completo para registrar personas interesadas.', 'Pertsona interesdunak erregistratzeko formulario osoa.' ),
					'render_callback' => array( __CLASS__, 'render_full_form_block' ),
					'category'        => 'widgets',
					'icon'            => 'forms',
					'keywords'        => array( 'leads', 'interesdunak', 'formulario' ),
					'supports'        => array(
						'align'    => array( 'wide', 'full' ),
						'multiple' => false,
					),
				)
			);
		}
	}

	/**
	 * Register settings fields on the ACF options page.
	 */
	private static function register_settings_fields() {
		acf_add_local_field_group(
			array(
				'key'      => 'group_komunikazioa_settings',
				'title'    => self::admin_label( 'Ajustes de Komunikazioa', 'Komunikazioa Ezarpenak' ),
				'fields'   => array(
					array(
						'key'   => 'field_komunikazioa_tab_smtp',
						'label' => self::admin_label( 'Servidor SMTP', 'SMTP zerbitzaria' ),
						'type'  => 'tab',
					),
					array(
						'key'          => 'field_komunikazioa_smtp_host',
						'label'        => self::admin_label( 'Servidor SMTP', 'SMTP zerbitzaria' ),
						'name'         => 'komunikazioa_smtp_host',
						'type'         => 'text',
						'instructions' => self::admin_label( 'Por ejemplo: smtp.gmail.com', 'Adibidez: smtp.gmail.com' ),
						'placeholder'  => 'smtp.gmail.com',
					),
					array(
						'key'           => 'field_komunikazioa_smtp_port',
						'label'         => self::admin_label( 'Puerto', 'Ataka' ),
						'name'          => 'komunikazioa_smtp_port',
						'type'          => 'number',
						'default_value' => 587,
						'min'           => 1,
						'max'           => 65535,
						'step'          => 1,
					),
					array(
						'key'           => 'field_komunikazioa_smtp_encryption',
						'label'         => self::admin_label( 'Cifrado', 'Zifratzea' ),
						'name'          => 'komunikazioa_smtp_encryption',
						'type'          => 'select',
						'choices'       => array(
							'tls'  => 'TLS',
							'ssl'  => 'SSL',
							'none' => self::admin_label( 'Ninguno', 'Bat ere ez' ),
						),
						'default_value' => 'tls',
						'return_format' => 'value',
						'ui'            => 1,
					),
					array(
						'key'          => 'field_komunikazioa_smtp_user',
						'label'        => self::admin_label( 'Usuario SMTP', 'SMTP erabiltzailea' ),
						'name'         => 'komunikazioa_smtp_user',
						'type'         => 'text',
						'instructions' => self::admin_label( 'Direccion de correo completa del buzon.', 'Postontziaren helbide osoa.' ),
					),
					array(
						'key'          => 'field_komunikazioa_smtp_password',
						'label'        => self::admin_label( 'Contrasena SMTP', 'SMTP pasahitza' ),
						'name'         => 'komunikazioa_smtp_password',
						'type'         => 'password',
						'instructions' => self::admin_label( 'Dejad este campo vacio al guardar si no quereis cambiar la contrasena.', 'Utzi hutsik gordetzean pasahitza aldatu nahi ez baduzue.' ),
					),
					array(
						'key'      => 'field_komunikazioa_smtp_status',
						'label'    => self::admin_label( 'Estado del envio', 'Bidalketaren egoera' ),
						'name'     => 'komunikazioa_smtp_status',
						'type'     => 'message',
						'message'  => '',
						'esc_html' => 0,
					),
					array(
						'key'   => 'field_komunikazioa_tab_sender',
						'label' => self::admin_label( 'Remitente', 'Igorlea' ),
						'type'  => 'tab',
					),
					array(
						'key'           => 'field_komunikazioa_from_name',
						'label'         => self::admin_label( 'Nombre del remitente', 'Nondik datorren izena' ),
						'name'          => 'komunikazioa_from_name',
						'type'          => 'text',
						'default_value' => get_bloginfo( 'name' ),
					),
					array(
						'key'           => 'field_komunikazioa_from_email',
						'label'         => self::admin_label( 'Email del remitente', 'Nondik datorren emaila' ),
						'name'          => 'komunikazioa_from_email',
						'type'          => 'email',
					),
					array(
						'key'   => 'field_komunikazioa_tab_audience',
						'label' => self::admin_label( 'Destinatarios', 'Hartzaileak' ),
						'type'  => 'tab',
					),
					array(
						'key'           => 'field_komunikazioa_member_roles',
						'label'         => self::admin_label( 'Perfiles de socios destinatarios', 'Bazkide hartzaileen profilak' ),
						'name'          => 'komunikazioa_member_roles',
						'type'          => 'select',
						'multiple'      => 1,
						'return_format' => 'value',
						'ui'            => 1,
						'choices'       => array(),
					),
					array(
						'key'           => 'field_komunikazioa_lead_notifications',
						'label'         => self::admin_label( 'Avisos internos de nuevas personas interesadas', 'Pertsona interesatu berrien barne-abisuak' ),
						'name'          => 'komunikazioa_lead_notifications',
						'type'          => 'textarea',
						'instructions'  => self::admin_label( 'Opcional. Estos correos reciben un aviso cuando alguien envia el formulario de Interesados. No define la lista de una campaña a Interesados: esa lista sale automaticamente de las personas interesadas registradas.', 'Aukerakoa. Email hauek abisu bat jasotzen dute norbaitek Interesdunak formularioa bidaltzen duenean. Ez du Interesdunentzako kanpaina baten zerrenda definitzen: zerrenda hori automatikoki ateratzen da erregistratutako pertsona interesatuetatik.' ),
						'new_lines'     => 'br',
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => self::SETTINGS_PAGE,
						),
					),
				),
			)
		);

		add_filter( 'acf/load_field/name=komunikazioa_member_roles', array( __CLASS__, 'populate_role_choices' ) );
		add_filter( 'acf/load_field/name=komunikazioa_smtp_status', array( __CLASS__, 'load_smtp_status_message_field' ) );
		add_filter( 'acf/update_value/name=komunikazioa_smtp_password', array( __CLASS__, 'preserve_smtp_password' ), 10, 3 );
	}

	/**
	 * Refresh the SMTP status message when the settings field loads.
	 *
	 * @param array $field Field configuration.
	 * @return array
	 */
	public static function load_smtp_status_message_field( $field ) {
		$field['message'] = self::get_smtp_settings_message();

		return $field;
	}

	/**
	 * Keep the stored SMTP password when the field is left blank on save.
	 *
	 * @param mixed  $value   Submitted value.
	 * @param string $post_id Options post ID.
	 * @return mixed
	 */
	public static function preserve_smtp_password( $value, $post_id ) {
		if ( self::SETTINGS_POST_ID !== (string) $post_id || '' !== (string) $value ) {
			return $value;
		}

		if ( ! function_exists( 'get_field' ) ) {
			return $value;
		}

		$existing = get_field( 'komunikazioa_smtp_password', self::SETTINGS_POST_ID, false );

		return $existing ? $existing : $value;
	}

	/**
	 * Configure PHPMailer from plugin settings when available.
	 *
	 * @param object $phpmailer PHPMailer instance.
	 * @return void
	 */
	public static function configure_phpmailer( $phpmailer ) {
		if ( ! self::is_smtp_configured() ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host = self::get_smtp_config( 'HOST' );
		$phpmailer->Port = (int) self::get_smtp_config( 'PORT', 587 );

		$encryption = (string) self::get_smtp_config( 'ENCRYPTION', 'tls' );
		if ( 'none' === $encryption ) {
			$encryption = '';
		}
		if ( '' !== $encryption ) {
			$phpmailer->SMTPSecure = $encryption;
		}

		$username = (string) self::get_smtp_config( 'USER' );
		$password = (string) self::get_smtp_config( 'PASSWORD' );

		$phpmailer->SMTPAuth = '' !== $username || '' !== $password;

		if ( '' !== $username ) {
			$phpmailer->Username = $username;
		}

		if ( '' !== $password ) {
			$phpmailer->Password = $password;
		}

		$from_email = self::get_mail_from_email();
		$from_name  = self::get_mail_from_name();

		if ( $from_email ) {
			$phpmailer->setFrom( $from_email, $from_name ? $from_name : get_bloginfo( 'name' ), false );
		}
	}

	/**
	 * Get a SMTP setting stored in the plugin options page.
	 *
	 * @param string $field_name ACF field name.
	 * @param mixed  $default    Default value.
	 * @return mixed
	 */
	private static function get_smtp_setting( $field_name, $default = '' ) {
		if ( ! function_exists( 'get_field' ) ) {
			return $default;
		}

		$value = get_field( $field_name, self::SETTINGS_POST_ID );

		if ( null === $value || false === $value || '' === $value ) {
			return $default;
		}

		return $value;
	}

	/**
	 * Get a SMTP configuration value from plugin settings.
	 *
	 * @param string $key Config suffix.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private static function get_smtp_config( $key, $default = '' ) {
		$map = array(
			'HOST'       => 'komunikazioa_smtp_host',
			'PORT'       => 'komunikazioa_smtp_port',
			'ENCRYPTION' => 'komunikazioa_smtp_encryption',
			'USER'       => 'komunikazioa_smtp_user',
			'PASSWORD'   => 'komunikazioa_smtp_password',
		);

		$key = strtoupper( (string) $key );
		if ( ! isset( $map[ $key ] ) ) {
			return $default;
		}

		return self::get_smtp_setting( $map[ $key ], $default );
	}

	/**
	 * Check whether SMTP was configured in plugin settings.
	 *
	 * @return bool
	 */
	private static function is_smtp_configured() {
		return '' !== (string) self::get_smtp_config( 'HOST' );
	}

	/**
	 * Get the effective sender name.
	 *
	 * @return string
	 */
	private static function get_mail_from_name() {
		$from_name = '';

		if ( function_exists( 'get_field' ) ) {
			$from_name = (string) get_field( 'komunikazioa_from_name', self::SETTINGS_POST_ID );
		}

		if ( '' === $from_name ) {
			$from_name = get_bloginfo( 'name' );
		}

		return sanitize_text_field( $from_name );
	}

	/**
	 * Get the effective sender email.
	 *
	 * @return string
	 */
	private static function get_mail_from_email() {
		$from_email = '';

		if ( function_exists( 'get_field' ) ) {
			$from_email = (string) get_field( 'komunikazioa_from_email', self::SETTINGS_POST_ID );
		}

		if ( '' === $from_email ) {
			$from_email = get_bloginfo( 'admin_email' );
		}

		return sanitize_email( $from_email );
	}

	/**
	 * Build the SMTP settings status message for the admin.
	 *
	 * @return string
	 */
	private static function get_smtp_settings_message() {
		if ( self::is_smtp_configured() ) {
			$host = esc_html( (string) self::get_smtp_config( 'HOST' ) );
			$port = esc_html( (string) self::get_smtp_config( 'PORT', 587 ) );
			$user = esc_html( (string) self::get_smtp_config( 'USER', self::admin_label( 'Sin usuario', 'Erabiltzailerik gabe' ) ) );

			return sprintf(
				__( '<div class="notice notice-success inline"><p>%s</p><p><strong>Zerbitzaria:</strong> %s<br><strong>Ataka:</strong> %s<br><strong>Erabiltzailea:</strong> %s</p></div>', 'komunikazioa' ),
				esc_html( self::admin_label( 'SMTP configurado en los ajustes del plugin.', 'SMTP pluginaren ezarpenetan konfiguratuta dago.' ) ),
				$host,
				$port,
				$user
			);
		}

		return sprintf(
			'<div class="notice notice-warning inline"><p>%s</p></div>',
			esc_html( self::admin_label( 'SMTP no configurado. El envio usa el transporte por defecto de WordPress hasta que indiqueis un servidor SMTP.', 'SMTP ez dago konfiguratuta. SMTP zerbitzari bat adierazi arte WordPressen garraio lehenetsia erabiltzen da.' ) )
		);
	}

	/**
	 * Build the dashboard notice for the SMTP test email.
	 *
	 * @return string
	 */
	private static function get_test_email_notice() {
		if ( empty( $_GET['komunikazioa_test_email'] ) ) {
			return '';
		}

		$status = sanitize_key( wp_unslash( $_GET['komunikazioa_test_email'] ) );

		if ( 'sent' === $status ) {
			return sprintf(
				'<div class="notice notice-success inline"><p>%s</p></div>',
				esc_html( self::admin_label( 'Correo de prueba enviado correctamente.', 'Probako mezua ondo bidali da.' ) )
			);
		}

		$message = self::admin_label( 'No se pudo enviar el correo de prueba.', 'Ezin izan da probako mezua bidali.' );
		if ( ! empty( $_GET['message'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
		}

		return sprintf(
			'<div class="notice notice-error inline"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Get the Komunikazioa dashboard URL.
	 *
	 * @param array $args Optional query args.
	 * @return string
	 */
	private static function get_dashboard_url( array $args = array() ) {
		$url = admin_url( 'admin.php?page=komunikazioa' );

		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		return $url;
	}

	/**
	 * Register campaign fields on the campaigns CPT.
	 */
	private static function register_campaign_fields() {
		acf_add_local_field_group(
			array(
				'key'      => 'group_komunikazioa_campaign',
				'title'    => self::admin_label( 'Campaña de comunicación', 'Komunikazioa mezua' ),
				'fields'   => array(
					array(
						'key'     => 'field_komunikazioa_campaign_type',
						'label'   => self::admin_label( 'Tipo de email', 'Email mota' ),
						'name'    => 'komunikazioa_campaign_type',
						'type'    => 'select',
						'choices' => array(
							'inscriptions' => __( 'Izen-emateak', 'komunikazioa' ),
							'renewal'      => __( 'Berritzea', 'komunikazioa' ),
						),
						'return_format' => 'value',
						'ui'            => 1,
					),
					array(
						'key'     => 'field_komunikazioa_target_profile',
						'label'   => __( 'Helburua', 'komunikazioa' ),
						'name'    => 'komunikazioa_target_profile',
						'type'    => 'select',
						'choices' => array(
							'socios'      => __( 'Bazkideak', 'komunikazioa' ),
							'interesdunak' => __( 'Interesdunak', 'komunikazioa' ),
						),
						'return_format' => 'value',
						'ui'            => 1,
					),
					array(
						'key'           => 'field_komunikazioa_subject',
						'label'         => __( 'Gaia', 'komunikazioa' ),
						'name'          => 'komunikazioa_subject',
						'type'          => 'text',
						'required'      => 1,
						'wrapper'       => array( 'width' => '100' ),
					),
					array(
						'key'          => 'field_komunikazioa_body',
						'label'        => __( 'Edukia', 'komunikazioa' ),
						'name'         => 'komunikazioa_body',
						'type'         => 'wysiwyg',
						'tabs'         => 'all',
						'toolbar'      => 'basic',
						'media_upload' => 0,
					),
					array(
						'key'           => 'field_komunikazioa_schedule_at',
						'label'         => self::admin_label( 'Fecha de programación', 'Programazio data' ),
						'name'          => 'komunikazioa_schedule_at',
						'type'          => 'date_time_picker',
						'display_format' => 'd/m/Y H:i',
						'return_format'  => 'Y-m-d H:i:s',
						'first_day'      => 1,
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => self::CPT,
						),
					),
				),
			)
		);
	}

	/**
	 * Populate roles in the settings select.
	 *
	 * @param array $field Field configuration.
	 * @return array
	 */
	public static function populate_role_choices( $field ) {
		$roles = wp_roles();
		$field['choices'] = array();

		if ( $roles && ! empty( $roles->roles ) ) {
			foreach ( $roles->roles as $role_slug => $role_data ) {
				$field['choices'][ $role_slug ] = translate_user_role( $role_data['name'] );
			}
		}

		return $field;
	}

	/**
	 * Render the dashboard landing page.
	 */
	public static function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Ez duzu orri hau ikusteko baimenik.', 'komunikazioa' ) );
		}

		$stats = self::get_dashboard_stats();
		$current_user = wp_get_current_user();
		$test_email   = $current_user instanceof \WP_User ? $current_user->user_email : get_bloginfo( 'admin_email' );
		?>
		<div class="wrap komunikazioa-wrap">
			<h1><?php echo esc_html__( 'Komunikazioa', 'komunikazioa' ); ?></h1>
			<p><?php echo esc_html__( 'Kanpainak, pertsona interesatuak eta programatutako bidalketak kudeatzeko tresna.', 'komunikazioa' ); ?></p>
			<?php echo wp_kses_post( self::get_test_email_notice() ); ?>

			<div class="card" style="max-width: 820px; margin-top: 20px;">
				<h2><?php echo esc_html__( 'Laburpena', 'komunikazioa' ); ?></h2>
				<ul style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;list-style:none;padding:0;margin:0;">
					<li><strong><?php echo esc_html__( 'Zirriborroak', 'komunikazioa' ); ?>:</strong> <?php echo esc_html( (string) $stats['draft'] ); ?></li>
					<li><strong><?php echo esc_html__( 'Programatuta', 'komunikazioa' ); ?>:</strong> <?php echo esc_html( (string) $stats['scheduled'] ); ?></li>
					<li><strong><?php echo esc_html__( 'Bidalita', 'komunikazioa' ); ?>:</strong> <?php echo esc_html( (string) $stats['sent'] ); ?></li>
					<li><strong><?php echo esc_html__( 'Hutsekin', 'komunikazioa' ); ?>:</strong> <?php echo esc_html( (string) $stats['failed'] ); ?></li>
					<li><strong><?php echo esc_html__( 'Pertsona interesatuak', 'komunikazioa' ); ?>:</strong> <?php echo esc_html( (string) $stats['leads'] ); ?></li>
					<li><strong><?php echo esc_html__( 'Entrega-hutsak', 'komunikazioa' ); ?>:</strong> <?php echo esc_html( (string) $stats['failed_deliveries'] ); ?></li>
				</ul>
			</div>

			<div class="card" style="max-width: 820px; margin-top: 20px;">
				<h2><?php echo esc_html__( 'Sarbide azkarrak', 'komunikazioa' ); ?></h2>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . self::CPT ) ); ?>"><?php echo esc_html__( 'Kanpaina berria', 'komunikazioa' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . self::CPT ) ); ?>"><?php echo esc_html__( 'Kanpainak ikusi', 'komunikazioa' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=komunikazioa-leads' ) ); ?>"><?php echo esc_html__( 'Interesatuak ikusi', 'komunikazioa' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_PAGE ) ); ?>"><?php echo esc_html( self::admin_label( 'Ajustes', 'Ezarpenak' ) ); ?></a>
				</p>
			</div>

			<div class="card" style="max-width: 820px; margin-top: 20px;">
				<h2><?php echo esc_html( self::admin_label( 'Configuracion de envio', 'Bidalketa konfigurazioa' ) ); ?></h2>
				<?php echo wp_kses_post( self::get_smtp_settings_message() ); ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 16px;">
					<input type="hidden" name="action" value="komunikazioa_send_test_email">
					<?php wp_nonce_field( 'komunikazioa_send_test_email', 'komunikazioa_test_email_nonce' ); ?>
					<p>
						<label for="komunikazioa_test_email"><strong><?php echo esc_html( self::admin_label( 'Email de prueba', 'Probako emaila' ) ); ?></strong></label><br>
						<input type="email" class="regular-text" id="komunikazioa_test_email" name="komunikazioa_test_email" value="<?php echo esc_attr( $test_email ); ?>" required>
					</p>
					<p>
						<button type="submit" class="button button-primary"><?php echo esc_html( self::admin_label( 'Enviar correo de prueba', 'Bidali probako mezua' ) ); ?></button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the leads page.
	 */
	public static function render_leads_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Ez duzu orri hau ikusteko baimenik.', 'komunikazioa' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::LEADS_TABLE;
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" );
		$failed = self::get_failed_delivery_rows();
		$attempted_col = self::get_logs_attempted_column();
		?>
		<div class="wrap komunikazioa-wrap">
			<h1><?php echo esc_html__( 'Pertsona interesatuak', 'komunikazioa' ); ?></h1>
			<p><?php echo esc_html__( 'Formulario publikoetatik erregistratutako pertsonak.', 'komunikazioa' ); ?></p>

			<div class="card" style="max-width: 100%; overflow:auto;">
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Data', 'komunikazioa' ); ?></th>
							<th><?php echo esc_html__( 'Formularioa', 'komunikazioa' ); ?></th>
							<th><?php echo esc_html__( 'Izena', 'komunikazioa' ); ?></th>
							<th><?php echo esc_html__( 'Email', 'komunikazioa' ); ?></th>
							<th><?php echo esc_html__( 'Telefonoa', 'komunikazioa' ); ?></th>
							<th><?php echo esc_html__( 'Herria', 'komunikazioa' ); ?></th>
							<th><?php echo esc_html__( 'Baldintzak', 'komunikazioa' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $rows ) : ?>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row->created_at ); ?></td>
									<td><?php echo esc_html( self::get_interest_form_type_label( $row->form_type ) ); ?></td>
									<td><?php echo esc_html( $row->full_name ); ?></td>
									<td><a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a></td>
									<td><?php echo esc_html( $row->phone ); ?></td>
									<td><?php echo esc_html( isset( $row->city ) ? $row->city : '' ); ?></td>
									<td><?php echo $row->terms_accepted ? esc_html__( 'Bai', 'komunikazioa' ) : esc_html__( 'Ez', 'komunikazioa' ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="7"><?php echo esc_html__( 'Oraindik ez dago erregistratutako pertsona interesaturik.', 'komunikazioa' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="card" style="max-width: 100%; overflow:auto; margin-top: 20px;">
				<h2><?php echo esc_html__( 'Entrega hutsak', 'komunikazioa' ); ?></h2>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Data', 'komunikazioa' ); ?></th>
							<th><?php echo esc_html__( 'Kanpaina', 'komunikazioa' ); ?></th>
							<th><?php echo esc_html__( 'Hartzailea', 'komunikazioa' ); ?></th>
							<th><?php echo esc_html__( 'Egoera', 'komunikazioa' ); ?></th>
							<th><?php echo esc_html__( 'Errorea', 'komunikazioa' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $failed ) : ?>
							<?php foreach ( $failed as $row ) : ?>
								<tr>
									<td><?php echo esc_html( isset( $row->{$attempted_col} ) ? $row->{$attempted_col} : '' ); ?></td>
									<td><?php echo esc_html( get_the_title( (int) $row->campaign_id ) ); ?></td>
									<td><?php echo esc_html( $row->recipient_email ); ?></td>
									<td><?php echo esc_html( $row->status ); ?></td>
									<td><?php echo esc_html( $row->error_message ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="5"><?php echo esc_html__( 'Oraindik ez dago entrega hutsik.', 'komunikazioa' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Get dashboard stats.
	 *
	 * @return array
	 */
	private static function get_dashboard_stats() {
		global $wpdb;

		$campaign_table = $wpdb->posts;
		$leads_table    = $wpdb->prefix . self::LEADS_TABLE;
		$logs_table     = $wpdb->prefix . self::LOGS_TABLE;

		return array(
			'draft'           => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$campaign_table} WHERE post_type = %s AND post_status = %s", self::CPT, 'draft' ) ),
			'scheduled'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$campaign_table} WHERE post_type = %s AND post_status = %s", self::CPT, 'future' ) ),
			'sent'            => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$campaign_table} WHERE post_type = %s AND post_status = %s", self::CPT, 'publish' ) ),
			'failed'          => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(pm.post_id) FROM {$wpdb->postmeta} pm INNER JOIN {$campaign_table} p ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value = %s", self::CPT, '_komunikazioa_delivery_state', 'failed' ) ),
			'leads'           => (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$leads_table}" ),
			'failed_deliveries'=> (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$logs_table} WHERE status = %s", 'failed' ) ),
		);
	}

	/**
	 * Get user-facing label for interest form type.
	 *
	 * @param string $form_type Stored form type key.
	 * @return string
	 */
	private static function get_interest_form_type_label( $form_type ) {
		$form_type = sanitize_key( (string) $form_type );

		if ( 'full' === $form_type ) {
			return __( 'Izen-emate eskaera', 'komunikazioa' );
		}

		if ( 'simple' === $form_type ) {
			return __( 'Interes adierazpena', 'komunikazioa' );
		}

		return $form_type;
	}

	/**
	 * Fetch failed delivery rows.
	 *
	 * @return array
	 */
	private static function get_failed_delivery_rows() {
		global $wpdb;
		$table = $wpdb->prefix . self::LOGS_TABLE;
		$attempted_col = self::get_logs_attempted_column();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY {$attempted_col} DESC LIMIT 100", 'failed' ) );
	}

	/**
	 * Return translatable admin label using Basque as source string.
	 *
	 * @param string $es Spanish label.
	 * @param string $eu Basque label.
	 * @return string
	 */
	private static function admin_label( $es, $eu ) {
		return __( (string) $eu, 'komunikazioa' );
	}

	/**
	 * Add columns to the campaigns list table.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function filter_campaign_columns( $columns ) {
		$columns['komunikazioa_target_profile'] = __( 'Profila', 'komunikazioa' );
		$columns['komunikazioa_state']          = __( 'Egoera', 'komunikazioa' );
		$columns['komunikazioa_schedule_at']    = __( 'Programatua', 'komunikazioa' );
		return $columns;
	}

	/**
	 * Render custom campaign columns.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_campaign_column( $column, $post_id ) {
		if ( 'komunikazioa_target_profile' === $column ) {
			$profile = (string) get_post_meta( $post_id, 'komunikazioa_target_profile', true );
			$labels  = array(
				'socios'       => __( 'Bazkideak', 'komunikazioa' ),
				'interesdunak' => __( 'Interesdunak', 'komunikazioa' ),
			);

			echo esc_html( isset( $labels[ $profile ] ) ? $labels[ $profile ] : $profile );
		}

		if ( 'komunikazioa_state' === $column ) {
			$state = get_post_meta( $post_id, '_komunikazioa_delivery_state', true );
			if ( ! $state ) {
				$state = 'draft' === get_post_status( $post_id ) ? 'draft' : ( 'future' === get_post_status( $post_id ) ? 'scheduled' : 'sent' );
			}

			$state_labels = array(
				'draft'     => __( 'Zirriborroa', 'komunikazioa' ),
				'scheduled' => __( 'Programatuta', 'komunikazioa' ),
				'sent'      => __( 'Bidalita', 'komunikazioa' ),
				'failed'    => __( 'Huts egin du', 'komunikazioa' ),
			);

			echo esc_html( isset( $state_labels[ $state ] ) ? $state_labels[ $state ] : $state );
		}

		if ( 'komunikazioa_schedule_at' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, 'komunikazioa_schedule_at', true ) );
		}
	}

	/**
	 * Keep scheduled campaigns in future status when they have a schedule date.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function sync_campaign_status_from_acf( $post_id ) {
		if ( self::CPT !== get_post_type( $post_id ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$scheduled_at = (string) get_field( 'komunikazioa_schedule_at', $post_id );
		$profile      = (string) get_field( 'komunikazioa_target_profile', $post_id );
		$subject      = (string) get_field( 'komunikazioa_subject', $post_id );

		if ( ! $subject || ! $profile ) {
			update_post_meta( $post_id, '_komunikazioa_delivery_state', 'draft' );
			return;
		}

		if ( $scheduled_at ) {
			update_post_meta( $post_id, '_komunikazioa_delivery_state', 'scheduled' );
			$scheduled_timestamp = strtotime( $scheduled_at );
			if ( $scheduled_timestamp && $scheduled_timestamp > current_time( 'timestamp' ) ) {
				self::maybe_update_post_status( $post_id, 'future', $scheduled_at );
				return;
			}
		}

		if ( 'publish' !== get_post_status( $post_id ) && $scheduled_at ) {
			self::maybe_update_post_status( $post_id, 'future', $scheduled_at );
		}
	}

	/**
	 * Update post status without causing recursion.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status   New status.
	 * @param string $date     Optional post date.
	 */
	private static function maybe_update_post_status( $post_id, $status, $date = '' ) {
		static $guard = false;

		if ( $guard ) {
			return;
		}

		$guard = true;

		$args = array(
			'ID'          => $post_id,
			'post_status' => $status,
		);

		if ( $date ) {
			$args['post_date']     = $date;
			$args['post_date_gmt'] = get_gmt_from_date( $date );
		}

		wp_update_post( $args );
		$guard = false;
	}

	/**
	 * Handle a lead form submit.
	 */
	public static function handle_lead_submit() {
		if ( empty( $_POST['komunikazioa_lead_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['komunikazioa_lead_nonce'] ) ), 'komunikazioa_submit_lead' ) ) {
			wp_die( esc_html__( 'Nonce baliogabea.', 'komunikazioa' ) );
		}

		$form_type = isset( $_POST['komunikazioa_form_type'] ) ? sanitize_key( wp_unslash( $_POST['komunikazioa_form_type'] ) ) : 'full';
		$full_name = isset( $_POST['komunikazioa_full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['komunikazioa_full_name'] ) ) : '';
		$email     = isset( $_POST['komunikazioa_email'] ) ? sanitize_email( wp_unslash( $_POST['komunikazioa_email'] ) ) : '';
		$phone     = isset( $_POST['komunikazioa_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['komunikazioa_phone'] ) ) : '';
		$city      = isset( $_POST['komunikazioa_city'] ) ? sanitize_text_field( wp_unslash( $_POST['komunikazioa_city'] ) ) : '';
		$terms     = ! empty( $_POST['komunikazioa_terms'] ) ? 1 : 0;
		$source_id = isset( $_POST['komunikazioa_source_post_id'] ) ? absint( wp_unslash( $_POST['komunikazioa_source_post_id'] ) ) : 0;
		$redirect  = ! empty( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : home_url( '/' );

		if ( empty( $email ) || ! is_email( $email ) ) {
			self::redirect_back( add_query_arg( 'komunikazioa_error', 'invalid-email', $redirect ) );
		}

		if ( ! $terms ) {
			self::redirect_back( add_query_arg( 'komunikazioa_error', 'terms', $redirect ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::LEADS_TABLE;

		$inserted = $wpdb->insert(
			$table,
			array(
				'form_type'      => $form_type,
				'full_name'      => $full_name,
				'email'          => $email,
				'phone'          => $phone,
				'city'           => $city,
				'birth_year'     => '',
				'terms_accepted' => $terms,
				'source_post_id' => $source_id,
				'ip_address'     => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			self::redirect_back( add_query_arg( 'komunikazioa_error', 'db', $redirect ) );
		}

		self::send_lead_notification_email(
			array(
				'form_type'  => $form_type,
				'full_name'  => $full_name,
				'email'      => $email,
				'phone'      => $phone,
				'city'       => $city,
			)
		);

		self::redirect_back( add_query_arg( 'komunikazioa_success', '1', $redirect ) );
	}

	/**
	 * Send a SMTP test email from the dashboard.
	 *
	 * @return void
	 */
	public static function handle_test_email_submit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Ez duzu ekintza hau egiteko baimenik.', 'komunikazioa' ) );
		}

		if ( empty( $_POST['komunikazioa_test_email_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['komunikazioa_test_email_nonce'] ) ), 'komunikazioa_send_test_email' ) ) {
			wp_die( esc_html__( 'Nonce baliogabea.', 'komunikazioa' ) );
		}

		$email = isset( $_POST['komunikazioa_test_email'] ) ? sanitize_email( wp_unslash( $_POST['komunikazioa_test_email'] ) ) : '';

		if ( ! $email || ! is_email( $email ) ) {
			wp_safe_redirect(
				self::get_dashboard_url(
					array(
						'komunikazioa_test_email' => 'failed',
						'message'                 => __( 'Sartu baliozko email bat probarako.', 'komunikazioa' ),
					)
				)
			);
			exit;
		}

		$subject = __( 'Komunikazioa bidalketa proba', 'komunikazioa' );
		$body    = '<p>' . esc_html__( 'Hau Komunikazioatik bidalitako probako mezu bat da.', 'komunikazioa' ) . '</p>';
		$body   .= '<p><strong>' . esc_html__( 'Gunea', 'komunikazioa' ) . ':</strong> ' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
		$body   .= '<p><strong>' . esc_html__( 'Data', 'komunikazioa' ) . ':</strong> ' . esc_html( current_time( 'mysql' ) ) . '</p>';

		$sent = self::send_html_mail( array( $email ), $subject, $body, '', 0 );

		if ( $sent ) {
			wp_safe_redirect( self::get_dashboard_url( array( 'komunikazioa_test_email' => 'sent' ) ) );
			exit;
		}

		wp_safe_redirect(
			self::get_dashboard_url(
				array(
					'komunikazioa_test_email' => 'failed',
					'message'                 => self::$mail_error ? self::$mail_error : __( 'wp_mail-ek false itzuli du proban.', 'komunikazioa' ),
				)
			)
		);
		exit;
	}

	/**
	 * Send the lead notification email to admin recipients.
	 *
	 * @param array $lead Lead data.
	 */
	private static function send_lead_notification_email( array $lead ) {
		$recipients = self::get_notification_recipients();
		if ( empty( $recipients ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: lead email */
			__( 'Pertsona interesatu berri bat: %s', 'komunikazioa' ),
			$lead['email']
		);

		$body = '<p><strong>' . esc_html__( 'Interesdunak formularioan pertsona interesatu berri bat jaso da', 'komunikazioa' ) . '</strong></p>';
		$body .= '<ul>';
		$body .= '<li><strong>' . esc_html__( 'Formularioa', 'komunikazioa' ) . ':</strong> ' . esc_html( self::get_interest_form_type_label( $lead['form_type'] ) ) . '</li>';
		$body .= '<li><strong>' . esc_html__( 'Izena', 'komunikazioa' ) . ':</strong> ' . esc_html( $lead['full_name'] ) . '</li>';
		$body .= '<li><strong>' . esc_html__( 'Email', 'komunikazioa' ) . ':</strong> ' . esc_html( $lead['email'] ) . '</li>';
		$body .= '<li><strong>' . esc_html__( 'Telefonoa', 'komunikazioa' ) . ':</strong> ' . esc_html( $lead['phone'] ) . '</li>';
		if ( ! empty( $lead['city'] ) ) {
			$body .= '<li><strong>' . esc_html__( 'Herria', 'komunikazioa' ) . ':</strong> ' . esc_html( $lead['city'] ) . '</li>';
		}
		$body .= '</ul>';

		self::send_html_mail( $recipients, $subject, $body );
	}

	/**
	 * Send a campaign to the selected audience.
	 *
	 * @param int $campaign_id Campaign post ID.
	 */
	private static function send_campaign( $campaign_id ) {
		$campaign_id = absint( $campaign_id );
		if ( ! $campaign_id ) {
			return;
		}

		$profile = (string) get_post_meta( $campaign_id, 'komunikazioa_target_profile', true );
		$subject = (string) get_post_meta( $campaign_id, 'komunikazioa_subject', true );
		$body    = (string) get_post_meta( $campaign_id, 'komunikazioa_body', true );

		if ( ! $profile || ! $subject || ! $body ) {
			update_post_meta( $campaign_id, '_komunikazioa_delivery_state', 'failed' );
			return;
		}

		$recipients = array();

		if ( 'socios' === $profile ) {
			$recipients = self::get_member_recipients();
		} elseif ( 'interesdunak' === $profile ) {
			$recipients = self::get_lead_recipients();
		}

		if ( empty( $recipients ) ) {
			update_post_meta( $campaign_id, '_komunikazioa_delivery_state', 'failed' );
			update_post_meta( $campaign_id, '_komunikazioa_delivery_error', __( 'Ez dago hartzailerik konfiguratuta.', 'komunikazioa' ) );
			return;
		}

		$has_failures = false;
		$prepared_body = self::build_campaign_html( $body );

		foreach ( $recipients as $recipient ) {
			$sent = self::send_html_mail( array( $recipient['email'] ), $subject, $prepared_body, $recipient['name'], $campaign_id );
			if ( ! $sent ) {
				$has_failures = true;
			}
		}

		update_post_meta( $campaign_id, '_komunikazioa_delivery_state', $has_failures ? 'failed' : 'sent' );
		update_post_meta( $campaign_id, '_komunikazioa_sent_at', current_time( 'mysql' ) );
		self::maybe_update_post_status( $campaign_id, 'publish' );
	}

	/**
	 * Cron callback to process due campaigns.
	 */
	public static function process_due_campaigns() {
		$campaigns = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => array( 'future', 'draft', 'publish' ),
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_komunikazioa_delivery_state',
						'value'   => 'scheduled',
						'compare' => '=',
					),
				),
			)
		);

		if ( empty( $campaigns ) ) {
			return;
		}

		$current_ts = current_time( 'timestamp' );
		foreach ( $campaigns as $campaign_id ) {
			$scheduled_at = (string) get_post_meta( $campaign_id, 'komunikazioa_schedule_at', true );
			if ( ! $scheduled_at ) {
				continue;
			}

			$scheduled_ts = strtotime( $scheduled_at );
			if ( ! $scheduled_ts || $scheduled_ts > $current_ts ) {
				continue;
			}

			self::send_campaign( $campaign_id );
		}
	}

	/**
	 * Build a simple HTML email wrapper.
	 *
	 * @param string $body Email body.
	 * @return string
	 */
	private static function build_campaign_html( $body ) {
		$logo = get_custom_logo();

		$html  = '<div style="background:#f4f1ea;padding:24px 0;font-family:Arial,Helvetica,sans-serif;color:#18223a;">';
		$html .= '<div style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #d8d1c5;border-radius:20px;overflow:hidden;">';
		$html .= '<div style="padding:28px 32px;border-bottom:1px solid #ece6dc;text-align:center;">';
		$html .= $logo ? $logo : '<div style="font-size:22px;font-weight:bold;letter-spacing:.04em;">' . esc_html( get_bloginfo( 'name' ) ) . '</div>';
		$html .= '</div>';
		$html .= '<div style="padding:32px;line-height:1.65;font-size:16px;">' . wp_kses_post( $body ) . '</div>';
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Send an HTML email.
	 *
	 * @param array  $to          Recipients.
	 * @param string $subject     Subject.
	 * @param string $body        HTML body.
	 * @param string $recipient_name Optional recipient name.
	 * @param int    $campaign_id Campaign ID.
	 * @return bool
	 */
	private static function send_html_mail( array $to, $subject, $body, $recipient_name = '', $campaign_id = 0 ) {
		self::$mail_error = null;

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_name  = self::get_mail_from_name();
		$from_email = self::get_mail_from_email();

		if ( $from_email ) {
			if ( $from_name ) {
				$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
			} else {
				$headers[] = 'From: ' . $from_email;
			}
		}

		add_filter( 'wp_mail_content_type', array( __CLASS__, 'force_html_mail_content_type' ) );
		$sent = wp_mail( $to, wp_strip_all_tags( $subject ), $body, $headers );
		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'force_html_mail_content_type' ) );

		self::log_mail_attempt(
			$campaign_id,
			isset( $to[0] ) ? $to[0] : '',
			$recipient_name,
			$sent ? 'sent' : 'failed',
			$sent ? '' : ( self::$mail_error ? self::$mail_error : __( 'wp_mail-ek false itzuli du.', 'komunikazioa' ) )
		);

		return (bool) $sent;
	}

	/**
	 * Force HTML content type while sending.
	 *
	 * @return string
	 */
	public static function force_html_mail_content_type() {
		return 'text/html; charset=UTF-8';
	}

	/**
	 * Capture the latest mail error.
	 *
	 * @param \WP_Error $error Error object.
	 */
	public static function capture_mail_error( $error ) {
		if ( is_wp_error( $error ) ) {
			self::$mail_error = $error->get_error_message();
		} elseif ( is_object( $error ) && isset( $error->errors ) ) {
			self::$mail_error = 'mail_failed';
		}
	}

	/**
	 * Log a mail attempt.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $email       Recipient email.
	 * @param string $name        Recipient name.
	 * @param string $status      sent|failed.
	 * @param string $message     Error message.
	 */
	private static function log_mail_attempt( $campaign_id, $email, $name, $status, $message ) {
		global $wpdb;

		$table = $wpdb->prefix . self::LOGS_TABLE;
		$attempted_col = self::get_logs_attempted_column();
		$data = array(
			'campaign_id'     => absint( $campaign_id ),
			'recipient_email' => sanitize_email( $email ),
			'recipient_name'  => sanitize_text_field( $name ),
			'status'          => sanitize_key( $status ),
			'error_message'   => $message ? sanitize_textarea_field( $message ) : '',
			'error_code'      => '',
			'sent_at'         => 'sent' === $status ? current_time( 'mysql' ) : null,
		);
		$data[ $attempted_col ] = current_time( 'mysql' );

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		$wpdb->insert(
			$table,
			$data,
			$formats
		);
	}

	/**
	 * Resolve attempted timestamp column name for backward compatibility.
	 *
	 * @return string
	 */
	private static function get_logs_attempted_column() {
		global $wpdb;

		static $column = null;
		if ( null !== $column ) {
			return $column;
		}

		$table = $wpdb->prefix . self::LOGS_TABLE;
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'attempted_at' ) );
		$column = $exists ? 'attempted_at' : 'tempted_at';

		return $column;
	}

	/**
	 * Get recipients from the configured admin notification list.
	 *
	 * @return array<int, string>
	 */
	private static function get_notification_recipients() {
		$raw = (string) get_field( 'komunikazioa_lead_notifications', self::SETTINGS_POST_ID );
		if ( ! $raw ) {
			return array( get_option( 'admin_email' ) );
		}

		$parts = preg_split( '/[\r\n,]+/', $raw );
		$emails = array();
		foreach ( (array) $parts as $part ) {
			$email = sanitize_email( trim( $part ) );
			if ( $email ) {
				$emails[] = $email;
			}
		}

		return array_values( array_unique( $emails ) );
	}

	/**
	 * Get recipients for members based on configured roles.
	 *
	 * @return array<int, array{name:string,email:string}>
	 */
	private static function get_member_recipients() {
		$roles = (array) get_field( 'komunikazioa_member_roles', self::SETTINGS_POST_ID );
		if ( empty( $roles ) ) {
			$roles = array( get_option( 'default_role', 'subscriber' ) );
		}

		$users = get_users(
			array(
				'role__in' => $roles,
				'fields'   => array( 'ID', 'user_email', 'display_name' ),
				'number'   => -1,
			)
		);

		$recipients = array();
		foreach ( $users as $user ) {
			if ( empty( $user->user_email ) ) {
				continue;
			}
			$recipients[] = array(
				'name'  => $user->display_name ? $user->display_name : $user->user_email,
				'email' => $user->user_email,
			);
		}

		return $recipients;
	}

	/**
	 * Get recipients from stored leads.
	 *
	 * @return array<int, array{name:string,email:string}>
	 */
	private static function get_lead_recipients() {
		global $wpdb;
		$table = $wpdb->prefix . self::LEADS_TABLE;
		$rows  = $wpdb->get_results( "SELECT full_name, email FROM {$table} WHERE email <> '' ORDER BY created_at DESC" );

		$recipients = array();
		foreach ( (array) $rows as $row ) {
			$recipients[] = array(
				'name'  => $row->full_name ? $row->full_name : $row->email,
				'email' => $row->email,
			);
		}

		return $recipients;
	}

	/**
	 * Helper to redirect the browser after form submit.
	 *
	 * @param string $url Redirect URL.
	 */
	private static function redirect_back( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Optional compatibility hook placeholder.
	 */
	public static function register_shortcode_compat() {
		// Intentionally left available for future shortcode fallbacks.
	}

	/**
	 * Render simple Interesdunak form block.
	 */
	public static function render_simple_form_block() {
		self::render_public_form( 'simple' );
	}

	/**
	 * Render full Interesdunak form block.
	 */
	public static function render_full_form_block() {
		self::render_public_form( 'full' );
	}

	/**
	 * Build the consent label HTML with the WordPress privacy policy link.
	 *
	 * @return string
	 */
	private static function get_form_terms_label_html() {
		$privacy_url = get_privacy_policy_url();

		if ( $privacy_url ) {
			$link = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $privacy_url ),
				esc_html( self::admin_label( 'la politica de privacidad', 'pribatutasun-politika' ) )
			);

			return wp_kses(
				sprintf(
					/* translators: %s: privacy policy link */
					__( 'Onartzen dut %s.', 'komunikazioa' ),
					$link
				),
				array(
					'a' => array(
						'href'   => true,
						'target' => true,
						'rel'    => true,
					),
				)
			);
		}

		return esc_html__( 'Baldintzak onartzen ditut.', 'komunikazioa' );
	}

	/**
	 * Render a public lead form.
	 *
	 * @param string $type Form type.
	 */
	private static function render_public_form( $type ) {
		$is_simple = ( 'simple' === $type );
		$message   = '';

		if ( isset( $_GET['komunikazioa_success'] ) ) {
			$message = '<p class="komunikazioa-form__notice komunikazioa-form__notice--success">' . esc_html__( 'Eskerrik asko. Zure eskaera jaso dugu.', 'komunikazioa' ) . '</p>';
		}

		if ( isset( $_GET['komunikazioa_error'] ) ) {
			$message = '<p class="komunikazioa-form__notice komunikazioa-form__notice--error">' . esc_html__( 'Errorea bidalketan. Saiatu berriz.', 'komunikazioa' ) . '</p>';
		}

		$form_id = 'komunikazioa-form-' . esc_attr( $type );

		echo '<div class="komunikazioa-form komunikazioa-form--' . esc_attr( $type ) . '">';
		echo wp_kses_post( $message );
		echo '<form id="' . esc_attr( $form_id ) . '" class="komunikazioa-form__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="komunikazioa_submit_lead" />';
		echo '<input type="hidden" name="komunikazioa_form_type" value="' . esc_attr( $type ) . '" />';
		echo '<input type="hidden" name="komunikazioa_source_post_id" value="' . esc_attr( get_the_ID() ? get_the_ID() : 0 ) . '" />';
		wp_nonce_field( 'komunikazioa_submit_lead', 'komunikazioa_lead_nonce' );

		if ( ! $is_simple ) {
			echo '<p class="komunikazioa-form__field">';
			echo '<label class="komunikazioa-form__label" for="' . esc_attr( $form_id ) . '-name">' . esc_html__( 'Izena', 'komunikazioa' ) . '</label>';
			echo '<input class="komunikazioa-form__input" id="' . esc_attr( $form_id ) . '-name" required type="text" name="komunikazioa_full_name" autocomplete="name" />';
			echo '</p>';
		}

		echo '<p class="komunikazioa-form__field">';
		echo '<label class="komunikazioa-form__label" for="' . esc_attr( $form_id ) . '-email">' . esc_html__( 'Email', 'komunikazioa' ) . '</label>';
		echo '<input class="komunikazioa-form__input" id="' . esc_attr( $form_id ) . '-email" required type="email" name="komunikazioa_email" autocomplete="email" />';
		echo '</p>';

		if ( ! $is_simple ) {
			echo '<p class="komunikazioa-form__field">';
			echo '<label class="komunikazioa-form__label" for="' . esc_attr( $form_id ) . '-phone">' . esc_html__( 'Telefonoa', 'komunikazioa' ) . '</label>';
			echo '<input class="komunikazioa-form__input" id="' . esc_attr( $form_id ) . '-phone" type="text" name="komunikazioa_phone" autocomplete="tel" />';
			echo '</p>';

			echo '<p class="komunikazioa-form__field">';
			echo '<label class="komunikazioa-form__label" for="' . esc_attr( $form_id ) . '-city">' . esc_html( self::admin_label( 'Poblacion', 'Herria' ) ) . '</label>';
			echo '<input class="komunikazioa-form__input" id="' . esc_attr( $form_id ) . '-city" type="text" name="komunikazioa_city" autocomplete="address-level2" />';
			echo '</p>';
		}

		echo '<p class="komunikazioa-form__terms">';
		echo '<label class="komunikazioa-form__terms-label" for="' . esc_attr( $form_id ) . '-terms">';
		echo '<input id="' . esc_attr( $form_id ) . '-terms" required type="checkbox" name="komunikazioa_terms" value="1" />';
		echo '<span>' . self::get_form_terms_label_html() . '</span>';
		echo '</label>';
		echo '</p>';

		echo '<button type="submit" class="komunikazioa-form__submit">' . esc_html__( 'Bidali', 'komunikazioa' ) . '</button>';
		echo '</form>';
		echo '</div>';
	}
}
