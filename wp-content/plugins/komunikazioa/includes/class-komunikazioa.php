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
	const IMPORT_PAGE = 'komunikazioa-import-users';
	const ONBOARDING_LOGO_URL = 'https://kostanelkartea.eus/wp-content/uploads/2026/07/logo_kostan.jpg';
	const PASSWORD_RESET_EXPIRATION_DAYS = 15;
	const IMPORT_ROSTER_OPTION = 'komunikazioa_import_roster';
	const IMPORT_JOB_OPTION = 'komunikazioa_import_job';
	const IMPORT_BATCH_CRON_HOOK = 'komunikazioa_process_import_batch';
	const IMPORT_BATCH_LOCK = 'komunikazioa_import_batch_lock';

	/** @var string|null */
	private static $mail_error = null;

	/** @var bool */
	private static $applying_plugin_smtp = false;

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
		add_filter( 'cron_schedules', array( __CLASS__, 'register_cron_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_due_campaigns' ) );
		add_action( 'admin_post_komunikazioa_send_test_email', array( __CLASS__, 'handle_test_email_submit' ) );
		add_action( 'admin_post_komunikazioa_import_users', array( __CLASS__, 'handle_import_users_submit' ) );
		add_action( 'admin_post_komunikazioa_send_onboarding_test_email', array( __CLASS__, 'handle_onboarding_test_email_submit' ) );
		add_action( 'admin_post_komunikazioa_resend_onboarding_mail', array( __CLASS__, 'handle_resend_onboarding_mail_submit' ) );
		add_action( self::IMPORT_BATCH_CRON_HOOK, array( __CLASS__, 'cron_process_import_batch' ), 10, 1 );
		add_action( 'wp_ajax_komunikazioa_process_import_batch', array( __CLASS__, 'ajax_process_import_batch' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_import_admin_assets' ) );
		add_filter( 'password_reset_expiration', array( __CLASS__, 'filter_password_reset_expiration' ) );
		add_action( 'init', array( __CLASS__, 'register_member_password_notification_filters' ), 20 );
		add_action( 'admin_post_komunikazioa_submit_lead', array( __CLASS__, 'handle_lead_submit' ) );
		add_action( 'admin_post_nopriv_komunikazioa_submit_lead', array( __CLASS__, 'handle_lead_submit' ) );
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

		wp_clear_scheduled_hook( self::IMPORT_BATCH_CRON_HOOK );
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
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
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
	public static function maybe_upgrade_tables() {
		global $wpdb;

		$table = $wpdb->prefix . self::LEADS_TABLE;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$city = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'city' ) );

		if ( ! $city ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN city varchar(190) NOT NULL DEFAULT '' AFTER phone" );
		}

		$first_name = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'first_name' ) );

		if ( ! $first_name ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN first_name varchar(100) NOT NULL DEFAULT '' AFTER full_name" );
		}

		$last_name = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'last_name' ) );

		if ( ! $last_name ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN last_name varchar(100) NOT NULL DEFAULT '' AFTER first_name" );
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
			'menu_name'          => self::admin_label( 'Comunicaciones', 'Komunikazioa' ),
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
			self::admin_label( 'Comunicaciones', 'Komunikazioa' ),
			self::admin_label( 'Comunicaciones', 'Komunikazioa' ),
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

		add_submenu_page(
			'komunikazioa',
			self::admin_label( 'Importar usuarios', 'Erabiltzaileak inportatu' ),
			self::admin_label( 'Importar usuarios', 'Erabiltzaileak inportatu' ),
			'manage_options',
			self::IMPORT_PAGE,
			array( __CLASS__, 'render_import_users_page' )
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
						'key'      => 'field_komunikazioa_smtp_test',
						'label'    => self::admin_label( 'Correo de prueba', 'Probako mezua' ),
						'name'     => 'komunikazioa_smtp_test',
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
						'key'          => 'field_komunikazioa_public_site_url',
						'label'        => self::admin_label( 'URL publica del sitio (enlaces en correos)', 'Gunearen URL publikoa (estekak mezuetan)' ),
						'name'         => 'komunikazioa_public_site_url',
						'type'         => 'url',
						'instructions' => self::admin_label(
							'Opcional. En produccion dejadlo vacio para usar la URL de WordPress. En local, indicad https://kostanelkartea.eus para que los enlaces de bienvenida apunten al dominio final.',
							'Aukerakoa. Produkzioan utzi hutsik WordPressen URLa erabiltzeko. Lokallean, idatzi https://kostanelkartea.eus ongietorri estekak azken domeinura joan daitezen.'
						),
						'placeholder'  => 'https://kostanelkartea.eus',
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
		add_filter( 'acf/load_field/name=komunikazioa_smtp_test', array( __CLASS__, 'load_smtp_test_email_field' ) );
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
	 * Render the SMTP test email form on the settings page.
	 *
	 * @param array $field Field configuration.
	 * @return array
	 */
	public static function load_smtp_test_email_field( $field ) {
		$current_user = wp_get_current_user();
		$test_email   = $current_user instanceof \WP_User ? $current_user->user_email : get_bloginfo( 'admin_email' );

		$field['message'] = self::get_test_email_notice() . self::render_smtp_test_email_form( $test_email );

		return $field;
	}

	/**
	 * Build the SMTP test email form markup.
	 *
	 * @param string $test_email Default recipient email.
	 * @return string
	 */
	private static function render_smtp_test_email_form( $test_email ) {
		ob_start();
		?>
		<p><?php echo esc_html( self::admin_label( 'Envia un correo de prueba para verificar la configuracion SMTP actual.', 'Bidali probako mezu bat uneko SMTP konfigurazioa egiaztatzeko.' ) ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="komunikazioa_send_test_email">
			<?php wp_nonce_field( 'komunikazioa_send_test_email', 'komunikazioa_test_email_nonce' ); ?>
			<p>
				<label for="komunikazioa_test_email"><strong><?php echo esc_html( self::admin_label( 'Email de prueba', 'Probako emaila' ) ); ?></strong></label><br>
				<input type="email" class="regular-text" id="komunikazioa_test_email" name="komunikazioa_test_email" value="<?php echo esc_attr( $test_email ); ?>" required>
			</p>
			<p>
				<button type="submit" class="button button-secondary"><?php echo esc_html( self::admin_label( 'Enviar correo de prueba', 'Bidali probako mezua' ) ); ?></button>
			</p>
		</form>
		<?php
		return (string) ob_get_clean();
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
		if ( ! self::$applying_plugin_smtp || ! self::is_smtp_configured() ) {
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
				'<div class="notice notice-success inline"><p>%s</p><p><strong>%s:</strong> %s<br><strong>%s:</strong> %s<br><strong>%s:</strong> %s</p></div>',
				esc_html( self::admin_label( 'SMTP configurado en los ajustes del plugin.', 'SMTP pluginaren ezarpenetan konfiguratuta dago.' ) ),
				esc_html( self::admin_label( 'Servidor', 'Zerbitzaria' ) ),
				$host,
				esc_html( self::admin_label( 'Puerto', 'Ataka' ) ),
				$port,
				esc_html( self::admin_label( 'Usuario', 'Erabiltzailea' ) ),
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
			$message = sanitize_text_field( rawurldecode( wp_unslash( $_GET['message'] ) ) );
		}

		return sprintf(
			'<div class="notice notice-error inline"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Get the Komunikazioa settings page URL.
	 *
	 * @param array $args Optional query args.
	 * @return string
	 */
	private static function get_settings_page_url( array $args = array() ) {
		$url = admin_url( 'admin.php?page=' . self::SETTINGS_PAGE );

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
							'inscriptions' => self::admin_label( 'Inscripciones', 'Izen-emateak' ),
							'renewal'      => self::admin_label( 'Renovacion', 'Berritzea' ),
						),
						'return_format' => 'value',
						'ui'            => 1,
					),
					array(
						'key'     => 'field_komunikazioa_target_profile',
						'label'   => self::admin_label( 'Destino', 'Helburua' ),
						'name'    => 'komunikazioa_target_profile',
						'type'    => 'select',
						'choices' => array(
							'socios'       => self::admin_label( 'Socios', 'Bazkideak' ),
							'interesdunak' => self::admin_label( 'Interesados', 'Interesdunak' ),
						),
						'return_format' => 'value',
						'ui'            => 1,
					),
					array(
						'key'           => 'field_komunikazioa_subject',
						'label'         => self::admin_label( 'Asunto', 'Gaia' ),
						'name'          => 'komunikazioa_subject',
						'type'          => 'text',
						'required'      => 1,
						'wrapper'       => array( 'width' => '100' ),
					),
					array(
						'key'          => 'field_komunikazioa_body',
						'label'        => self::admin_label( 'Contenido', 'Edukia' ),
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
			wp_die( esc_html( self::admin_label( 'No tienes permiso para ver esta pagina.', 'Ez duzu orri hau ikusteko baimenik.' ) ) );
		}

		$stats = self::get_dashboard_stats();
		?>
		<div class="wrap komunikazioa-wrap">
			<h1><?php echo esc_html( self::admin_label( 'Comunicaciones', 'Komunikazioa' ) ); ?></h1>
			<p><?php echo esc_html( self::admin_label( 'Herramienta para gestionar campanas, personas interesadas y envios programados.', 'Kanpainak, pertsona interesatuak eta programatutako bidalketak kudeatzeko tresna.' ) ); ?></p>

			<div class="card" style="max-width: 820px; margin-top: 20px;">
				<h2><?php echo esc_html( self::admin_label( 'Resumen', 'Laburpena' ) ); ?></h2>
				<ul style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;list-style:none;padding:0;margin:0;">
					<li><strong><?php echo esc_html( self::admin_label( 'Borradores', 'Zirriborroak' ) ); ?>:</strong> <?php echo esc_html( (string) $stats['draft'] ); ?></li>
					<li><strong><?php echo esc_html( self::admin_label( 'Programados', 'Programatuta' ) ); ?>:</strong> <?php echo esc_html( (string) $stats['scheduled'] ); ?></li>
					<li><strong><?php echo esc_html( self::admin_label( 'Enviados', 'Bidalita' ) ); ?>:</strong> <?php echo esc_html( (string) $stats['sent'] ); ?></li>
					<li><strong><?php echo esc_html( self::admin_label( 'Fallidos', 'Hutsekin' ) ); ?>:</strong> <?php echo esc_html( (string) $stats['failed'] ); ?></li>
					<li><strong><?php echo esc_html( self::admin_label( 'Personas interesadas', 'Pertsona interesatuak' ) ); ?>:</strong> <?php echo esc_html( (string) $stats['leads'] ); ?></li>
					<li><strong><?php echo esc_html( self::admin_label( 'Entregas fallidas', 'Entrega-hutsak' ) ); ?>:</strong> <?php echo esc_html( (string) $stats['failed_deliveries'] ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the leads page.
	 */
	public static function render_leads_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( self::admin_label( 'No tienes permiso para ver esta pagina.', 'Ez duzu orri hau ikusteko baimenik.' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . self::LEADS_TABLE;
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200" );
		$failed = self::get_failed_delivery_rows();
		$attempted_col = self::get_logs_attempted_column();
		?>
		<div class="wrap komunikazioa-wrap">
			<h1><?php echo esc_html( self::admin_label( 'Personas interesadas', 'Pertsona interesatuak' ) ); ?></h1>
			<p><?php echo esc_html( self::admin_label( 'Personas registradas desde los formularios publicos.', 'Formulario publikoetatik erregistratutako pertsonak.' ) ); ?></p>

			<div class="card" style="max-width: 100%; overflow:auto;">
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th><?php echo esc_html( self::admin_label( 'Fecha', 'Data' ) ); ?></th>
							<th><?php echo esc_html( self::admin_label( 'Formulario', 'Formularioa' ) ); ?></th>
							<th><?php echo esc_html( self::admin_label( 'Nombre', 'Izena' ) ); ?></th>
							<th><?php echo esc_html( self::admin_label( 'Apellidos', 'Abizenak' ) ); ?></th>
							<th><?php echo esc_html( self::admin_label( 'Email', 'Email' ) ); ?></th>
							<th><?php echo esc_html( self::admin_label( 'Telefono', 'Telefonoa' ) ); ?></th>
							<th><?php echo esc_html( self::admin_label( 'Poblacion', 'Herria' ) ); ?></th>
							<th><?php echo esc_html( self::admin_label( 'Condiciones', 'Baldintzak' ) ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $rows ) : ?>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row->created_at ); ?></td>
									<td><?php echo esc_html( self::get_interest_form_type_label( $row->form_type ) ); ?></td>
									<td><?php echo esc_html( self::get_lead_first_name( $row ) ); ?></td>
									<td><?php echo esc_html( self::get_lead_last_name( $row ) ); ?></td>
									<td><a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a></td>
									<td><?php echo esc_html( $row->phone ); ?></td>
									<td><?php echo esc_html( isset( $row->city ) ? $row->city : '' ); ?></td>
									<td><?php echo $row->terms_accepted ? esc_html( self::admin_label( 'Si', 'Bai' ) ) : esc_html( self::admin_label( 'No', 'Ez' ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="8"><?php echo esc_html( self::admin_label( 'Todavia no hay personas interesadas registradas.', 'Oraindik ez dago erregistratutako pertsona interesaturik.' ) ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="card" style="max-width: 100%; overflow:auto; margin-top: 20px;">
				<h2><?php echo esc_html( self::admin_label( 'Entregas fallidas', 'Entrega hutsak' ) ); ?></h2>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th><?php echo esc_html( self::admin_label( 'Fecha', 'Data' ) ); ?></th>
							<th><?php echo esc_html( self::admin_label( 'Campana', 'Kanpaina' ) ); ?></th>
							<th><?php echo esc_html( self::admin_label( 'Destinatario', 'Hartzailea' ) ); ?></th>
							<th><?php echo esc_html( self::admin_label( 'Estado', 'Egoera' ) ); ?></th>
							<th><?php echo esc_html( self::admin_label( 'Error', 'Errorea' ) ); ?></th>
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
							<tr><td colspan="5"><?php echo esc_html( self::admin_label( 'Todavia no hay entregas fallidas.', 'Oraindik ez dago entrega hutsik.' ) ); ?></td></tr>
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
			return self::admin_label( 'Solicitud de inscripcion', 'Izen-emate eskaera' );
		}

		if ( 'simple' === $form_type ) {
			return self::admin_label( 'Manifestacion de interes', 'Interes adierazpena' );
		}

		return $form_type;
	}

	/**
	 * Get the first name stored for a lead row.
	 *
	 * @param object $row Lead database row.
	 * @return string
	 */
	private static function get_lead_first_name( $row ) {
		if ( isset( $row->first_name ) && '' !== trim( (string) $row->first_name ) ) {
			return (string) $row->first_name;
		}

		return '';
	}

	/**
	 * Get the last name stored for a lead row.
	 *
	 * @param object $row Lead database row.
	 * @return string
	 */
	private static function get_lead_last_name( $row ) {
		if ( isset( $row->last_name ) && '' !== trim( (string) $row->last_name ) ) {
			return (string) $row->last_name;
		}

		return '';
	}

	/**
	 * Build a display name for a lead row.
	 *
	 * @param object $row Lead database row.
	 * @return string
	 */
	private static function get_lead_full_name( $row ) {
		$first_name = self::get_lead_first_name( $row );
		$last_name  = self::get_lead_last_name( $row );
		$full_name  = trim( $first_name . ' ' . $last_name );

		if ( '' !== $full_name ) {
			return $full_name;
		}

		return isset( $row->full_name ) ? (string) $row->full_name : '';
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
	 * Whether the current admin UI should use Spanish labels.
	 *
	 * @return bool
	 */
	private static function is_spanish_admin_ui() {
		$locale = is_admin() ? get_user_locale() : determine_locale();

		return str_starts_with( strtolower( (string) $locale ), 'es' );
	}

	/**
	 * Return a bilingual admin label.
	 *
	 * @param string $es Spanish label.
	 * @param string $eu Basque label.
	 * @return string
	 */
	public static function admin_label( $es, $eu ) {
		return self::is_spanish_admin_ui() ? (string) $es : (string) $eu;
	}

	/**
	 * Return an escaped bilingual admin label.
	 *
	 * @param string $es Spanish label.
	 * @param string $eu Basque label.
	 * @return string
	 */
	private static function admin_text( $es, $eu ) {
		return esc_html( self::admin_label( $es, $eu ) );
	}

	/**
	 * Add columns to the campaigns list table.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function filter_campaign_columns( $columns ) {
		$columns['komunikazioa_target_profile'] = self::admin_label( 'Perfil', 'Profila' );
		$columns['komunikazioa_state']          = self::admin_label( 'Estado', 'Egoera' );
		$columns['komunikazioa_schedule_at']    = self::admin_label( 'Programado', 'Programatua' );
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
				'socios'       => self::admin_label( 'Socios', 'Bazkideak' ),
				'interesdunak' => self::admin_label( 'Interesados', 'Interesdunak' ),
			);

			echo esc_html( isset( $labels[ $profile ] ) ? $labels[ $profile ] : $profile );
		}

		if ( 'komunikazioa_state' === $column ) {
			$state = get_post_meta( $post_id, '_komunikazioa_delivery_state', true );
			if ( ! $state ) {
				$state = 'draft' === get_post_status( $post_id ) ? 'draft' : ( 'future' === get_post_status( $post_id ) ? 'scheduled' : 'sent' );
			}

			$state_labels = array(
				'draft'     => self::admin_label( 'Borrador', 'Zirriborroa' ),
				'scheduled' => self::admin_label( 'Programado', 'Programatuta' ),
				'sent'      => self::admin_label( 'Enviado', 'Bidalita' ),
				'failed'    => self::admin_label( 'Fallido', 'Huts egin du' ),
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

		$form_type  = isset( $_POST['komunikazioa_form_type'] ) ? sanitize_key( wp_unslash( $_POST['komunikazioa_form_type'] ) ) : 'full';
		$first_name = isset( $_POST['komunikazioa_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['komunikazioa_first_name'] ) ) : '';
		$last_name  = isset( $_POST['komunikazioa_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['komunikazioa_last_name'] ) ) : '';
		$full_name  = trim( $first_name . ' ' . $last_name );
		$email      = isset( $_POST['komunikazioa_email'] ) ? sanitize_email( wp_unslash( $_POST['komunikazioa_email'] ) ) : '';
		$phone     = isset( $_POST['komunikazioa_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['komunikazioa_phone'] ) ) : '';
		$city      = isset( $_POST['komunikazioa_city'] ) ? sanitize_text_field( wp_unslash( $_POST['komunikazioa_city'] ) ) : '';
		$terms     = ! empty( $_POST['komunikazioa_terms'] ) ? 1 : 0;
		$source_id = isset( $_POST['komunikazioa_source_post_id'] ) ? absint( wp_unslash( $_POST['komunikazioa_source_post_id'] ) ) : 0;
		$redirect  = ! empty( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : home_url( '/' );

		if ( empty( $email ) || ! is_email( $email ) ) {
			self::redirect_back( add_query_arg( 'komunikazioa_error', 'invalid-email', $redirect ) );
		}

		if ( 'full' === $form_type && ( '' === $first_name || '' === $last_name ) ) {
			self::redirect_back( add_query_arg( 'komunikazioa_error', 'invalid-name', $redirect ) );
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
				'first_name'     => $first_name,
				'last_name'      => $last_name,
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
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			self::redirect_back( add_query_arg( 'komunikazioa_error', 'db', $redirect ) );
		}

		self::send_lead_notification_email(
			array(
				'form_type'  => $form_type,
				'full_name'  => $full_name,
				'first_name' => $first_name,
				'last_name'  => $last_name,
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
				self::get_settings_page_url(
					array(
						'komunikazioa_test_email' => 'failed',
						'message'                 => rawurlencode( self::admin_label( 'Introduce un email valido.', 'Sartu baliozko email bat.' ) ),
					)
				)
			);
			exit;
		}

		$subject = self::admin_label( 'Prueba de envio Komunikazioa', 'Komunikazioa bidalketa proba' );
		$body    = '<p>' . esc_html( self::admin_label( 'Este es un correo de prueba enviado desde Komunikazioa.', 'Hau Komunikazioatik bidalitako probako mezu bat da.' ) ) . '</p>';
		$body   .= '<p><strong>' . esc_html( self::admin_label( 'Sitio', 'Gunea' ) ) . ':</strong> ' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
		$body   .= '<p><strong>' . esc_html( self::admin_label( 'Fecha', 'Data' ) ) . ':</strong> ' . esc_html( current_time( 'mysql' ) ) . '</p>';

		$sent = self::send_html_mail( array( $email ), $subject, $body, '', 0 );

		if ( $sent ) {
			wp_safe_redirect( self::get_settings_page_url( array( 'komunikazioa_test_email' => 'sent' ) ) );
			exit;
		}

		wp_safe_redirect(
			self::get_settings_page_url(
				array(
					'komunikazioa_test_email' => 'failed',
					'message'                 => rawurlencode( self::$mail_error ? self::$mail_error : self::admin_label( 'wp_mail devolvio false en la prueba.', 'wp_mail-ek false itzuli du proban.' ) ),
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

		if ( ! empty( $lead['first_name'] ) || ! empty( $lead['last_name'] ) ) {
			$body .= '<li><strong>' . esc_html__( 'Izena', 'komunikazioa' ) . ':</strong> ' . esc_html( $lead['first_name'] ) . '</li>';
			$body .= '<li><strong>' . esc_html__( 'Abizenak', 'komunikazioa' ) . ':</strong> ' . esc_html( $lead['last_name'] ) . '</li>';
		} elseif ( ! empty( $lead['full_name'] ) ) {
			$body .= '<li><strong>' . esc_html__( 'Izena', 'komunikazioa' ) . ':</strong> ' . esc_html( $lead['full_name'] ) . '</li>';
		}

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
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'capture_mail_error' ) );

		self::$applying_plugin_smtp = true;
		$sent = wp_mail( $to, wp_strip_all_tags( $subject ), $body, $headers );
		self::$applying_plugin_smtp = false;

		remove_action( 'wp_mail_failed', array( __CLASS__, 'capture_mail_error' ) );
		remove_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ) );
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
		$rows  = $wpdb->get_results( "SELECT full_name, first_name, last_name, email FROM {$table} WHERE email <> '' ORDER BY created_at DESC" );

		$recipients = array();
		foreach ( (array) $rows as $row ) {
			$display_name = self::get_lead_full_name( $row );
			$recipients[] = array(
				'name'  => $display_name ? $display_name : $row->email,
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
	 * Get the import users admin URL.
	 *
	 * @param array $args Optional query args.
	 * @return string
	 */
	private static function get_import_page_url( array $args = array() ) {
		$url = admin_url( 'admin.php?page=' . self::IMPORT_PAGE );

		if ( ! empty( $args ) ) {
			$url = add_query_arg( $args, $url );
		}

		return $url;
	}

	/**
	 * Get the active background import job.
	 *
	 * @return array|null
	 */
	private static function get_active_import_job() {
		$job = get_option( self::IMPORT_JOB_OPTION, null );

		return is_array( $job ) ? $job : null;
	}

	/**
	 * Save the active import job.
	 *
	 * @param array $job Import job.
	 */
	private static function save_import_job( array $job ) {
		update_option( self::IMPORT_JOB_OPTION, $job, false );
	}

	/**
	 * Clear the active import job and related cron events.
	 *
	 * @param string $job_id Job ID.
	 */
	private static function clear_import_job( $job_id = '' ) {
		delete_option( self::IMPORT_JOB_OPTION );

		if ( $job_id ) {
			wp_clear_scheduled_hook( self::IMPORT_BATCH_CRON_HOOK, array( $job_id ) );
		} else {
			wp_clear_scheduled_hook( self::IMPORT_BATCH_CRON_HOOK );
		}
	}

	/**
	 * Schedule a fallback cron batch if the browser stops polling.
	 *
	 * @param string $job_id Job ID.
	 */
	private static function schedule_import_batch_fallback( $job_id ) {
		$job_id = (string) $job_id;
		if ( '' === $job_id ) {
			return;
		}

		if ( ! wp_next_scheduled( self::IMPORT_BATCH_CRON_HOOK, array( $job_id ) ) ) {
			wp_schedule_single_event( time() + 60, self::IMPORT_BATCH_CRON_HOOK, array( $job_id ) );
		}
	}

	/**
	 * Try to acquire the import batch lock.
	 *
	 * @return bool
	 */
	private static function acquire_import_batch_lock() {
		if ( get_transient( self::IMPORT_BATCH_LOCK ) ) {
			return false;
		}

		return (bool) set_transient( self::IMPORT_BATCH_LOCK, '1', 120 );
	}

	/**
	 * Release the import batch lock.
	 */
	private static function release_import_batch_lock() {
		delete_transient( self::IMPORT_BATCH_LOCK );
	}

	/**
	 * Build progress payload for the import UI.
	 *
	 * @param array $job Import job.
	 * @return array
	 */
	private static function build_import_job_progress( array $job ) {
		$total  = isset( $job['total'] ) ? (int) $job['total'] : 0;
		$cursor = isset( $job['cursor'] ) ? (int) $job['cursor'] : 0;
		$report = isset( $job['report'] ) && is_array( $job['report'] ) ? $job['report'] : User_Importer::empty_report( 'full' );
		$stats  = User_Importer::summarize_report( $report );
		$percent = $total > 0 ? min( 100, (int) round( ( $cursor / $total ) * 100 ) ) : 0;

		return array(
			'job_id'  => isset( $job['id'] ) ? (string) $job['id'] : '',
			'status'  => isset( $job['status'] ) ? (string) $job['status'] : 'running',
			'cursor'  => $cursor,
			'total'   => $total,
			'percent' => $percent,
			'stats'   => $stats,
		);
	}

	/**
	 * Persist the final import report and clear the job.
	 *
	 * @param array $job Import job.
	 */
	private static function finalize_import_job( array $job ) {
		$report = isset( $job['report'] ) && is_array( $job['report'] ) ? $job['report'] : User_Importer::empty_report( 'full' );
		$user_id = isset( $job['started_by'] ) ? (int) $job['started_by'] : get_current_user_id();

		set_transient( self::get_import_report_transient_key( $user_id ), $report, 7 * DAY_IN_SECONDS );
		self::clear_import_job( isset( $job['id'] ) ? (string) $job['id'] : '' );
	}

	/**
	 * Process one import batch and update the stored job.
	 *
	 * @param array $job Import job.
	 * @return array Updated job.
	 */
	private static function run_import_batch( array $job ) {
		if ( empty( $job['status'] ) || 'running' !== $job['status'] ) {
			return $job;
		}

		if ( ! self::acquire_import_batch_lock() ) {
			return $job;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		User_Importer::process_import_batch( $job );
		self::save_import_job( $job );

		if ( 'completed' === $job['status'] || 'failed' === $job['status'] ) {
			self::finalize_import_job( $job );
		} else {
			self::schedule_import_batch_fallback( isset( $job['id'] ) ? (string) $job['id'] : '' );
		}

		self::release_import_batch_lock();

		return $job;
	}

	/**
	 * AJAX handler for batched import processing.
	 */
	public static function ajax_process_import_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => self::admin_label( 'No tienes permiso para realizar esta accion.', 'Ez duzu ekintza hau egiteko baimenik.' ) ),
				403
			);
		}

		check_ajax_referer( 'komunikazioa_import_batch', 'nonce' );

		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$job    = self::get_active_import_job();

		if ( ! is_array( $job ) || '' === $job_id || ( isset( $job['id'] ) && (string) $job['id'] !== $job_id ) ) {
			wp_send_json_error(
				array( 'message' => self::admin_label( 'No hay ninguna importacion activa.', 'Ez dago inportazio aktiborik.' ) ),
				404
			);
		}

		if ( 'running' !== $job['status'] ) {
			wp_send_json_success(
				array_merge(
					self::build_import_job_progress( $job ),
					array( 'reload' => in_array( $job['status'], array( 'completed', 'failed' ), true ) )
				)
			);
		}

		$job = self::run_import_batch( $job );

		if ( 'running' === $job['status'] ) {
			$job = self::get_active_import_job();
		}

		if ( ! is_array( $job ) ) {
			wp_send_json_success(
				array(
					'status'  => 'completed',
					'cursor'  => 0,
					'total'   => 0,
					'percent' => 100,
					'stats'   => User_Importer::summarize_report( User_Importer::empty_report( 'full' ) ),
					'reload'  => true,
				)
			);
		}

		wp_send_json_success(
			array_merge(
				self::build_import_job_progress( $job ),
				array( 'reload' => in_array( $job['status'], array( 'completed', 'failed' ), true ) )
			)
		);
	}

	/**
	 * Cron fallback for batched import processing.
	 *
	 * @param string $job_id Job ID.
	 */
	public static function cron_process_import_batch( $job_id ) {
		$job = self::get_active_import_job();
		if ( ! is_array( $job ) || ( isset( $job['id'] ) && (string) $job['id'] !== (string) $job_id ) ) {
			return;
		}

		if ( 'running' !== $job['status'] ) {
			return;
		}

		self::run_import_batch( $job );
	}

	/**
	 * Enqueue assets for the import batch UI.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue_import_admin_assets( $hook ) {
		if ( 'komunikazioa_page_' . self::IMPORT_PAGE !== $hook ) {
			return;
		}

		$job = self::get_active_import_job();
		if ( ! is_array( $job ) || 'running' !== $job['status'] ) {
			return;
		}

		wp_enqueue_script(
			'komunikazioa-import-batch',
			KOMUNIKAZIOA_URL . '/assets/js/import-batch.js',
			array(),
			KOMUNIKAZIOA_VERSION,
			true
		);

		wp_localize_script(
			'komunikazioa-import-batch',
			'komunikazioaImportBatch',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'komunikazioa_import_batch' ),
				'jobId'    => isset( $job['id'] ) ? (string) $job['id'] : '',
				'progress' => self::build_import_job_progress( $job ),
				'i18n'     => array(
					'processing' => self::admin_label( 'Procesando importacion...', 'Inportazioa prozesatzen...' ),
					'completed'  => self::admin_label( 'Importacion completada. Actualizando informe...', 'Inportazioa osatuta. Txostena eguneratzen...' ),
					'failed'     => self::admin_label( 'La importacion se detuvo por un error.', 'Inportazioa errore batengatik gelditu da.' ),
					'waiting'    => self::admin_label( 'Esperando al siguiente lote...', 'Hurrengo zain...' ),
					'of'         => self::admin_label( 'de', '-' ),
				),
			)
		);
	}

	/**
	 * Render the batch import progress panel.
	 *
	 * @param array $job Import job.
	 */
	private static function render_import_batch_progress( array $job ) {
		$progress = self::build_import_job_progress( $job );
		$stats    = $progress['stats'];
		?>
		<div class="card" id="komunikazioa-import-progress" style="max-width: 920px; margin-top: 20px;">
			<h2><?php echo esc_html( self::admin_label( 'Importacion en curso', 'Inportazioa abian' ) ); ?></h2>
			<p><?php echo esc_html( self::admin_label( 'Los usuarios se crean y se envian los correos en lotes. Puedes dejar esta pagina abierta o volver mas tarde; el proceso continuara en segundo plano.', 'Erabiltzaileak sortu eta mezuak lotetan bidaltzen dira. Orri hau irekia utzi dezakezu edo geroago itzuli; prozesua atzeko planoan jarraituko du.' ) ); ?></p>
			<progress id="komunikazioa-import-progress-bar" max="100" value="<?php echo esc_attr( (string) $progress['percent'] ); ?>" style="width:100%;height:24px;"></progress>
			<p id="komunikazioa-import-progress-text" style="margin-top:12px;">
				<?php
				echo esc_html(
					sprintf(
						'%1$s %2$d %3$s %4$d',
						self::admin_label( 'Filas procesadas:', 'Prozesatutako errenkadak:' ),
						(int) $progress['cursor'],
						self::admin_label( 'de', '/' ),
						(int) $progress['total']
					)
				);
				?>
			</p>
			<ul style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;list-style:none;padding:0;margin:16px 0 0;">
				<li><strong><?php echo esc_html( self::admin_label( 'Creados', 'Sortuak' ) ); ?>:</strong> <span id="komunikazioa-import-stat-created"><?php echo esc_html( (string) $stats['created'] ); ?></span></li>
				<li><strong><?php echo esc_html( self::admin_label( 'Omitidos', 'Baztertuak' ) ); ?>:</strong> <span id="komunikazioa-import-stat-skipped"><?php echo esc_html( (string) $stats['skipped'] ); ?></span></li>
				<li><strong><?php echo esc_html( self::admin_label( 'Correos enviados', 'Bidalitako mezuak' ) ); ?>:</strong> <span id="komunikazioa-import-stat-mailed"><?php echo esc_html( (string) $stats['mailed'] ); ?></span></li>
				<li><strong><?php echo esc_html( self::admin_label( 'Fallos de envio', 'Bidalketa hutsegiteak' ) ); ?>:</strong> <span id="komunikazioa-import-stat-mail-failed"><?php echo esc_html( (string) $stats['mail_failed'] ); ?></span></li>
				<li><strong><?php echo esc_html( self::admin_label( 'Errores', 'Erroreak' ) ); ?>:</strong> <span id="komunikazioa-import-stat-errors"><?php echo esc_html( (string) $stats['errors'] ); ?></span></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render the user import page.
	 */
	public static function render_import_users_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( self::admin_label( 'No tienes permiso para ver esta pagina.', 'Ez duzu orri hau ikusteko baimenik.' ) ) );
		}

		$current_user = wp_get_current_user();
		$test_email   = $current_user instanceof \WP_User ? $current_user->user_email : get_bloginfo( 'admin_email' );
		$preview_email = 'socio@ejemplo.com';
		$preview_url   = self::get_onboarding_reset_url( 'socio_ejemplo', 'preview-key' );
		$preview_html  = self::build_onboarding_mail_html( $preview_email, $preview_url, false );
		$stored_report = get_transient( self::get_import_report_transient_key() );
		$active_job    = self::get_active_import_job();
		?>
		<div class="wrap komunikazioa-wrap">
			<h1><?php echo esc_html( self::admin_label( 'Importar usuarios', 'Erabiltzaileak inportatu' ) ); ?></h1>
			<p><?php echo esc_html( self::admin_label( 'Importa socios desde un CSV con email y nombre de usuario. La importacion completa se procesa en lotes para evitar timeouts. Cada usuario recibira un correo de bienvenida para crear su contraseña.', 'Inportatu bazkideak emaila eta erabiltzaile-izena dituen CSV batetik. Inportazio osoa timeoutak saihesteko lotetan prozesatzen da. Erabiltzaile bakoitzak ongietorri mezu bat jasoko du bere pasahitza sortzeko.' ) ); ?></p>
			<?php echo wp_kses_post( self::get_import_notice() ); ?>

			<?php if ( is_array( $active_job ) && in_array( $active_job['status'], array( 'running', 'failed' ), true ) ) : ?>
				<?php self::render_import_batch_progress( $active_job ); ?>
			<?php endif; ?>

			<div class="card" style="max-width: 920px; margin-top: 20px;">
				<h2><?php echo esc_html( self::admin_label( 'Vista previa del correo de bienvenida', 'Ongietorri mezuaren aurrebista' ) ); ?></h2>
				<div style="border:1px solid #d8d1c5;border-radius:12px;overflow:hidden;">
					<?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>

			<div class="card" style="max-width: 920px; margin-top: 20px;">
				<h2><?php echo esc_html( self::admin_label( 'Probar correo de bienvenida', 'Ongietorri mezua probatu' ) ); ?></h2>
				<p><?php echo esc_html( self::admin_label( 'Envia la plantilla real a tu bandeja sin crear usuarios. El enlace sera funcional para tu cuenta de administrador.', 'Bidali txantiloia zure sarrera-ontzira erabiltzaileak sortu gabe. Esteka zure administratzaile konturako baliagarria izango da.' ) ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="komunikazioa_send_onboarding_test_email">
					<?php wp_nonce_field( 'komunikazioa_send_onboarding_test_email', 'komunikazioa_onboarding_test_nonce' ); ?>
					<p>
						<label for="komunikazioa_onboarding_test_email"><strong><?php echo esc_html( self::admin_label( 'Email de prueba', 'Probako emaila' ) ); ?></strong></label><br>
						<input type="email" class="regular-text" id="komunikazioa_onboarding_test_email" name="komunikazioa_onboarding_test_email" value="<?php echo esc_attr( $test_email ); ?>" required>
					</p>
					<p>
						<button type="submit" class="button"><?php echo esc_html( self::admin_label( 'Enviar correo de prueba', 'Bidali probako mezua' ) ); ?></button>
					</p>
				</form>
			</div>

			<div class="card" style="max-width: 920px; margin-top: 20px;">
				<h2><?php echo esc_html( self::admin_label( 'Importar CSV', 'CSV inportatu' ) ); ?></h2>
				<p><?php echo esc_html( self::admin_label( 'Formato: email,user_login (cabecera opcional). Rol asignado: socios. La importacion completa procesa 10 usuarios por lote.', 'Formatua: email,user_login (goiburua aukerakoa). Esleitutako rola: socios. Inportazio osoak 10 erabiltzaile prozesatzen ditu loteko.' ) ); ?></p>
				<?php if ( is_array( $active_job ) && 'running' === $active_job['status'] ) : ?>
					<p><em><?php echo esc_html( self::admin_label( 'Hay una importacion en curso. Espera a que finalice para iniciar otra.', 'Inportazio bat abian da. Itxaron amaitu arte beste bat hasteko.' ) ); ?></em></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="komunikazioa_import_users">
					<?php wp_nonce_field( 'komunikazioa_import_users', 'komunikazioa_import_users_nonce' ); ?>
					<p>
						<label for="komunikazioa_import_csv"><strong><?php echo esc_html( self::admin_label( 'Archivo CSV', 'CSV fitxategia' ) ); ?></strong></label><br>
						<input type="file" id="komunikazioa_import_csv" name="komunikazioa_import_csv" accept=".csv,text/csv" required>
					</p>
					<p style="display:flex;gap:8px;flex-wrap:wrap;">
						<button type="submit" class="button" name="komunikazioa_import_mode" value="simulate"><?php echo esc_html( self::admin_label( 'Simular importacion', 'Inportazioa simulatu' ) ); ?></button>
						<button type="submit" class="button button-secondary" name="komunikazioa_import_mode" value="test"><?php echo esc_html( self::admin_label( 'Probar importacion', 'Inportazioa probatu' ) ); ?></button>
						<button type="submit" class="button button-primary" name="komunikazioa_import_mode" value="full" <?php disabled( is_array( $active_job ) && 'running' === $active_job['status'] ); ?> onclick="return confirm('<?php echo esc_js( self::admin_label( 'Se importaran todos los usuarios validos del CSV en lotes. ¿Continuar?', 'CSVko erabiltzaile baliozko guztiak lotetan inportatuko dira. Jarraitu?' ) ); ?>');"><?php echo esc_html( self::admin_label( 'Importar todo', 'Dena inportatu' ) ); ?></button>
					</p>
				</form>
			</div>

			<?php if ( is_array( $stored_report ) ) : ?>
				<div class="card" style="max-width: 920px; margin-top: 20px;">
					<?php self::render_import_report( $stored_report ); ?>
				</div>
			<?php endif; ?>

			<?php
			$roster = self::get_import_roster();
			if ( ! empty( $roster ) ) :
				uasort(
					$roster,
					static function ( $a, $b ) {
						return strcmp( (string) $b['updated_at'], (string) $a['updated_at'] );
					}
				);
				?>
				<div class="card" style="max-width: 920px; margin-top: 20px;">
					<h2><?php echo esc_html( self::admin_label( 'Socios importados', 'Inportatutako bazkideak' ) ); ?></h2>
					<p><?php echo esc_html( self::admin_label( 'Estado de activacion y reenvio del correo de bienvenida. El enlace caduca a los 15 dias.', 'Aktibazio egoera eta ongietorri mezuaren berriro bidalketa. Esteka 15 egunetan iraungitzen da.' ) ); ?></p>
					<?php self::render_onboarding_users_table( array_keys( $roster ), '', true ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle CSV import submissions.
	 */
	public static function handle_import_users_submit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( self::admin_label( 'No tienes permiso para realizar esta accion.', 'Ez duzu ekintza hau egiteko baimenik.' ) ) );
		}

		if ( empty( $_POST['komunikazioa_import_users_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['komunikazioa_import_users_nonce'] ) ), 'komunikazioa_import_users' ) ) {
			wp_die( esc_html( self::admin_label( 'Nonce no valido.', 'Nonce baliogabea.' ) ) );
		}

		$mode = isset( $_POST['komunikazioa_import_mode'] ) ? sanitize_key( wp_unslash( $_POST['komunikazioa_import_mode'] ) ) : 'simulate';
		if ( ! in_array( $mode, array( 'simulate', 'test', 'full' ), true ) ) {
			$mode = 'simulate';
		}

		if ( empty( $_FILES['komunikazioa_import_csv']['tmp_name'] ) ) {
			wp_safe_redirect( self::get_import_page_url( array( 'komunikazioa_import' => 'failed', 'message' => rawurlencode( self::admin_label( 'Selecciona un archivo CSV.', 'Hautatu CSV fitxategi bat.' ) ) ) ) );
			exit;
		}

		$file = $_FILES['komunikazioa_import_csv'];
		if ( ! empty( $file['error'] ) ) {
			wp_safe_redirect( self::get_import_page_url( array( 'komunikazioa_import' => 'failed', 'message' => rawurlencode( self::admin_label( 'Error al subir el archivo.', 'Errorea fitxategia igotzean.' ) ) ) ) );
			exit;
		}

		if ( (int) $file['size'] > User_Importer::MAX_FILE_BYTES ) {
			wp_safe_redirect( self::get_import_page_url( array( 'komunikazioa_import' => 'failed', 'message' => rawurlencode( self::admin_label( 'El archivo CSV es demasiado grande.', 'CSV fitxategia handiegia da.' ) ) ) ) );
			exit;
		}

		$parsed = User_Importer::parse_csv_file( $file['tmp_name'] );
		if ( is_wp_error( $parsed ) ) {
			wp_safe_redirect( self::get_import_page_url( array( 'komunikazioa_import' => 'failed', 'message' => rawurlencode( $parsed->get_error_message() ) ) ) );
			exit;
		}

		if ( 'full' === $mode ) {
			$active_job = self::get_active_import_job();
			if ( is_array( $active_job ) && 'running' === $active_job['status'] ) {
				wp_safe_redirect(
					self::get_import_page_url(
						array(
							'komunikazioa_import' => 'failed',
							'message'             => rawurlencode(
								self::admin_label(
									'Ya hay una importacion en curso. Espera a que finalice.',
									'Inportazio bat abian da dagoeneko. Itxaron amaitu arte.'
								)
							),
						)
					)
				);
				exit;
			}

			$job = User_Importer::create_import_job( $parsed, get_current_user_id() );
			update_option( self::IMPORT_JOB_OPTION, $job, false );
			self::run_import_batch( $job );

			$job = self::get_active_import_job();
			if ( ! is_array( $job ) ) {
				wp_safe_redirect( self::get_import_page_url( array( 'komunikazioa_import' => 'done', 'mode' => 'full' ) ) );
				exit;
			}

			self::schedule_import_batch_fallback( $job['id'] );

			wp_safe_redirect(
				self::get_import_page_url(
					array(
						'komunikazioa_import' => 'queued',
						'job'                 => $job['id'],
					)
				)
			);
			exit;
		}

		$report = User_Importer::process_rows( $parsed, $mode );
		set_transient( self::get_import_report_transient_key(), $report, 7 * DAY_IN_SECONDS );

		wp_safe_redirect( self::get_import_page_url( array( 'komunikazioa_import' => 'done', 'mode' => $mode ) ) );
		exit;
	}

	/**
	 * Send a welcome email preview without creating users.
	 */
	public static function handle_onboarding_test_email_submit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( self::admin_label( 'No tienes permiso para realizar esta accion.', 'Ez duzu ekintza hau egiteko baimenik.' ) ) );
		}

		if ( empty( $_POST['komunikazioa_onboarding_test_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['komunikazioa_onboarding_test_nonce'] ) ), 'komunikazioa_send_onboarding_test_email' ) ) {
			wp_die( esc_html( self::admin_label( 'Nonce no valido.', 'Nonce baliogabea.' ) ) );
		}

		$email = isset( $_POST['komunikazioa_onboarding_test_email'] ) ? sanitize_email( wp_unslash( $_POST['komunikazioa_onboarding_test_email'] ) ) : '';
		if ( ! $email || ! is_email( $email ) ) {
			wp_safe_redirect( self::get_import_page_url( array( 'komunikazioa_onboarding_test' => 'failed', 'message' => rawurlencode( self::admin_label( 'Introduce un email valido.', 'Sartu baliozko email bat.' ) ) ) ) );
			exit;
		}

		$sent = self::send_onboarding_test_mail( $email );
		if ( $sent ) {
			wp_safe_redirect( self::get_import_page_url( array( 'komunikazioa_onboarding_test' => 'sent' ) ) );
			exit;
		}

		wp_safe_redirect(
			self::get_import_page_url(
				array(
					'komunikazioa_onboarding_test' => 'failed',
					'message'                        => rawurlencode( self::get_last_mail_error_message() ),
				)
			)
		);
		exit;
	}

	/**
	 * Resend onboarding welcome email to an imported user.
	 */
	public static function handle_resend_onboarding_mail_submit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( self::admin_label( 'No tienes permiso para realizar esta accion.', 'Ez duzu ekintza hau egiteko baimenik.' ) ) );
		}

		if ( empty( $_POST['komunikazioa_resend_onboarding_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['komunikazioa_resend_onboarding_nonce'] ) ), 'komunikazioa_resend_onboarding_mail' ) ) {
			wp_die( esc_html( self::admin_label( 'Nonce no valido.', 'Nonce baliogabea.' ) ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$user    = $user_id ? get_user_by( 'id', $user_id ) : false;

		if ( ! ( $user instanceof \WP_User ) ) {
			wp_safe_redirect( self::get_import_page_url( array( 'komunikazioa_resend' => 'failed', 'message' => rawurlencode( self::admin_label( 'Usuario no encontrado.', 'Erabiltzailea ez da aurkitu.' ) ) ) ) );
			exit;
		}

		if ( self::is_user_onboarding_complete( $user_id ) ) {
			wp_safe_redirect( self::get_import_page_url( array( 'komunikazioa_resend' => 'already_active' ) ) );
			exit;
		}

		$sent = self::send_user_onboarding_mail( $user );
		if ( $sent ) {
			self::add_to_import_roster( $user );
			wp_safe_redirect( self::get_import_page_url( array( 'komunikazioa_resend' => 'sent' ) ) );
			exit;
		}

		wp_safe_redirect(
			self::get_import_page_url(
				array(
					'komunikazioa_resend' => 'failed',
					'message'             => rawurlencode( self::get_last_mail_error_message() ),
				)
			)
		);
		exit;
	}

	/**
	 * Transient key for the latest import report.
	 *
	 * @return string
	 */
	private static function get_import_report_transient_key( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		return 'komunikazioa_import_report_' . (int) $user_id;
	}

	/**
	 * Build import page notices.
	 *
	 * @return string
	 */
	private static function get_import_notice() {
		$html = '';

		if ( ! empty( $_GET['komunikazioa_onboarding_test'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['komunikazioa_onboarding_test'] ) );
			if ( 'sent' === $status ) {
				$html .= sprintf(
					'<div class="notice notice-success inline"><p>%s</p></div>',
					esc_html( self::admin_label( 'Correo de bienvenida de prueba enviado correctamente.', 'Ongietorri probako mezua ondo bidali da.' ) )
				);
			} elseif ( 'failed' === $status ) {
				$message = self::admin_label( 'No se pudo enviar el correo de prueba.', 'Ezin izan da probako mezua bidali.' );
				if ( ! empty( $_GET['message'] ) ) {
					$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
				}
				$html .= sprintf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $message ) );
			}
		}

		if ( ! empty( $_GET['komunikazioa_import'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['komunikazioa_import'] ) );
			if ( 'done' === $status ) {
				$mode = ! empty( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'simulate';
				$labels = array(
					'simulate' => self::admin_label( 'Simulacion completada.', 'Simulazioa osatuta.' ),
					'test'     => self::admin_label( 'Prueba de importacion completada.', 'Inportazio proba osatuta.' ),
					'full'     => self::admin_label( 'Importacion completada.', 'Inportazioa osatuta.' ),
				);
				$html .= sprintf(
					'<div class="notice notice-success inline"><p>%s</p></div>',
					esc_html( isset( $labels[ $mode ] ) ? $labels[ $mode ] : $labels['simulate'] )
				);
			} elseif ( 'queued' === $status ) {
				$html .= sprintf(
					'<div class="notice notice-info inline"><p>%s</p></div>',
					esc_html( self::admin_label( 'Importacion iniciada. Procesando usuarios en lotes...', 'Inportazioa hasi da. Erabiltzaileak lotetan prozesatzen...' ) )
				);
			} elseif ( 'failed' === $status ) {
				$message = self::admin_label( 'No se pudo procesar el CSV.', 'Ezin izan da CSVa prozesatu.' );
				if ( ! empty( $_GET['message'] ) ) {
					$message = sanitize_text_field( rawurldecode( wp_unslash( $_GET['message'] ) ) );
				}
				$html .= sprintf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $message ) );
			}
		}

		if ( ! empty( $_GET['komunikazioa_resend'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['komunikazioa_resend'] ) );
			if ( 'sent' === $status ) {
				$html .= sprintf(
					'<div class="notice notice-success inline"><p>%s</p></div>',
					esc_html( self::admin_label( 'Correo de bienvenida reenviado correctamente.', 'Ongietorri mezua ondo berriro bidali da.' ) )
				);
			} elseif ( 'already_active' === $status ) {
				$html .= sprintf(
					'<div class="notice notice-warning inline"><p>%s</p></div>',
					esc_html( self::admin_label( 'El usuario ya ha completado el alta y no necesita un nuevo correo.', 'Erabiltzaileak alta osatu du eta ez du mezu berririk behar.' ) )
				);
			} elseif ( 'failed' === $status ) {
				$message = self::admin_label( 'No se pudo reenviar el correo.', 'Ezin izan da mezua berriro bidali.' );
				if ( ! empty( $_GET['message'] ) ) {
					$message = sanitize_text_field( rawurldecode( wp_unslash( $_GET['message'] ) ) );
				}
				$html .= sprintf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $message ) );
			}
		}

		return $html;
	}

	/**
	 * Render an import report table.
	 *
	 * @param array $report Import report.
	 */
	private static function render_import_report( array $report ) {
		$mode_labels = array(
			'simulate' => self::admin_label( 'Informe de simulacion', 'Simulazio txostena' ),
			'test'     => self::admin_label( 'Informe de prueba de importacion', 'Inportazio probaren txostena' ),
			'full'     => self::admin_label( 'Informe de importacion', 'Inportazio txostena' ),
		);
		$mode = isset( $report['mode'] ) ? (string) $report['mode'] : 'simulate';
		?>
		<h2><?php echo esc_html( isset( $mode_labels[ $mode ] ) ? $mode_labels[ $mode ] : $mode_labels['simulate'] ); ?></h2>
		<?php self::render_import_report_section( self::admin_label( 'Validos / creados', 'Baliozkoak / sortuak' ), isset( $report['created'] ) ? $report['created'] : array() ); ?>
		<?php self::render_import_report_section( self::admin_label( 'Omitidos', 'Baztertuak' ), isset( $report['skipped'] ) ? $report['skipped'] : array() ); ?>
		<?php self::render_import_report_section( self::admin_label( 'Correos enviados', 'Bidalitako mezuak' ), isset( $report['mailed'] ) ? $report['mailed'] : array() ); ?>
		<?php self::render_import_report_section( self::admin_label( 'Fallos de envio', 'Bidalketa hutsegiteak' ), isset( $report['mail_failed'] ) ? $report['mail_failed'] : array() ); ?>
		<?php self::render_import_report_section( self::admin_label( 'Errores', 'Erroreak' ), isset( $report['errors'] ) ? $report['errors'] : array() ); ?>
		<?php
		$user_ids = self::collect_report_user_ids( $report );
		if ( ! empty( $user_ids ) && 'simulate' !== $mode ) {
			self::render_onboarding_users_table(
				$user_ids,
				self::admin_label( 'Seguimiento y reenvio', 'Jarraipena eta berriro bidalketa' ),
				true
			);
		}
	}

	/**
	 * Collect unique user IDs from an import report.
	 *
	 * @param array $report Import report.
	 * @return int[]
	 */
	private static function collect_report_user_ids( array $report ) {
		$ids = array();

		foreach ( array( 'created', 'skipped', 'mailed', 'mail_failed' ) as $section ) {
			if ( empty( $report[ $section ] ) || ! is_array( $report[ $section ] ) ) {
				continue;
			}

			foreach ( $report[ $section ] as $row ) {
				if ( ! empty( $row['user_id'] ) ) {
					$ids[ (int) $row['user_id'] ] = true;
				}
			}
		}

		return array_keys( $ids );
	}

	/**
	 * Render onboarding status table with optional resend action.
	 *
	 * @param int[]  $user_ids      User IDs to display.
	 * @param string $title         Optional section title.
	 * @param bool   $allow_resend  Whether to show resend buttons.
	 */
	private static function render_onboarding_users_table( array $user_ids, $title = '', $allow_resend = true ) {
		$rows = array();

		foreach ( $user_ids as $user_id ) {
			$user = get_user_by( 'id', (int) $user_id );
			if ( ! ( $user instanceof \WP_User ) ) {
				continue;
			}

			$rows[] = $user;
		}

		if ( empty( $rows ) ) {
			return;
		}

		if ( $title ) {
			?>
			<h3><?php echo esc_html( $title ); ?> (<?php echo esc_html( (string) count( $rows ) ); ?>)</h3>
			<?php
		}
		?>
		<table class="widefat striped" style="margin-bottom:16px;">
			<thead>
				<tr>
					<th><?php echo esc_html( self::admin_label( 'Email', 'Email' ) ); ?></th>
					<th><?php echo esc_html( self::admin_label( 'Usuario', 'Erabiltzailea' ) ); ?></th>
					<th><?php echo esc_html( self::admin_label( 'Estado', 'Egoera' ) ); ?></th>
					<th><?php echo esc_html( self::admin_label( 'Ultimo correo', 'Azken mezua' ) ); ?></th>
					<?php if ( $allow_resend ) : ?>
						<th><?php echo esc_html( self::admin_label( 'Accion', 'Ekintza' ) ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $user ) : ?>
					<?php
					$is_active  = self::is_user_onboarding_complete( $user->ID );
					$last_mail  = (string) get_user_meta( $user->ID, 'komunikazioa_last_onboarding_mail', true );
					$status_cls = $is_active ? 'komunikazioa-status-active' : 'komunikazioa-status-pending';
					?>
					<tr>
						<td><?php echo esc_html( $user->user_email ); ?></td>
						<td><?php echo esc_html( $user->user_login ); ?></td>
						<td><span class="<?php echo esc_attr( $status_cls ); ?>"><?php echo esc_html( self::get_user_onboarding_status_label( $user->ID ) ); ?></span></td>
						<td><?php echo $last_mail ? esc_html( $last_mail ) : '—'; ?></td>
						<?php if ( $allow_resend ) : ?>
							<td>
								<?php if ( $is_active ) : ?>
									<span style="color:#646970;"><?php echo esc_html( self::admin_label( 'Ya activo', 'Dagoeneko aktiboa' ) ); ?></span>
								<?php else : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
										<input type="hidden" name="action" value="komunikazioa_resend_onboarding_mail">
										<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user->ID ); ?>">
										<?php wp_nonce_field( 'komunikazioa_resend_onboarding_mail', 'komunikazioa_resend_onboarding_nonce' ); ?>
										<button type="submit" class="button button-small"><?php echo esc_html( self::admin_label( 'Reenviar correo', 'Mezua berriro bidali' ) ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render one import report section.
	 *
	 * @param string $title Section title.
	 * @param array  $rows  Section rows.
	 */
	private static function render_import_report_section( $title, array $rows ) {
		?>
		<h3><?php echo esc_html( $title ); ?> (<?php echo esc_html( (string) count( $rows ) ); ?>)</h3>
		<?php if ( empty( $rows ) ) : ?>
			<p><?php echo esc_html( self::admin_label( 'Sin registros.', 'Erregistrorik gabe.' ) ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="margin-bottom:16px;">
				<thead>
					<tr>
						<th><?php echo esc_html( self::admin_label( 'Linea', 'Lerroa' ) ); ?></th>
						<th><?php echo esc_html( self::admin_label( 'Email', 'Email' ) ); ?></th>
						<th><?php echo esc_html( self::admin_label( 'Usuario', 'Erabiltzailea' ) ); ?></th>
						<th><?php echo esc_html( self::admin_label( 'Detalle', 'Xehetasuna' ) ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $row['line'] ) ? (string) $row['line'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $row['email'] ) ? (string) $row['email'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $row['login'] ) ? (string) $row['login'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $row['message'] ) ? (string) $row['message'] : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Get onboarding logo URL.
	 *
	 * @return string
	 */
	public static function get_onboarding_logo_url() {
		return apply_filters( 'komunikazioa_onboarding_logo_url', self::ONBOARDING_LOGO_URL );
	}

	/**
	 * Build onboarding welcome email HTML.
	 *
	 * @param string $email     Recipient email shown in body.
	 * @param string $reset_url Password setup URL.
	 * @param bool   $is_test   Whether this is a test email.
	 * @return string
	 */
	public static function build_onboarding_mail_html( $email, $reset_url, $is_test = false ) {
		$logo_url = esc_url( self::get_onboarding_logo_url() );
		$email    = sanitize_email( $email );
		$reset_url = esc_url( $reset_url );

		$html  = '<div style="background:#f4f1ea;padding:32px 16px;font-family:Arial,Helvetica,sans-serif;color:#18223a;">';
		$html .= '<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #d8d1c5;border-radius:20px;overflow:hidden;">';
		$html .= '<div style="padding:32px 32px 16px;text-align:center;">';
		$html .= '<img src="' . $logo_url . '" alt="Kostan Elkartea" width="120" style="display:block;margin:0 auto 24px;max-width:120px;height:auto;">';
		$html .= '<h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;font-weight:700;color:#18223a;">Ongi etorri</h1>';
		$html .= '<p style="margin:0 0 4px;font-size:16px;line-height:1.6;color:#18223a;">Zure emaila / tu email</p>';
		$html .= '<p style="margin:0 0 8px;font-size:16px;line-height:1.6;color:#18223a;"><strong>' . esc_html( $email ) . '</strong></p>';
		$html .= '</div>';
		$html .= '<div style="padding:8px 32px 32px;text-align:center;">';
		$html .= '<a href="' . $reset_url . '" style="display:inline-block;background:#00b7c6;color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;padding:14px 28px;border-radius:999px;">Crea tu contraseña</a>';
		$html .= '<p style="margin:24px 0 0;font-size:13px;line-height:1.5;color:#5f6778;">' . esc_html( self::get_password_reset_expiration_mail_text() ) . '</p>';

		if ( $is_test ) {
			$html .= '<p style="margin:12px 0 0;font-size:13px;line-height:1.5;color:#5f6778;">Probako mezua / Correo de prueba.</p>';
		}

		$html .= '</div></div></div>';

		return $html;
	}

	/**
	 * Get onboarding email subject.
	 *
	 * @return string
	 */
	public static function get_onboarding_mail_subject() {
		return 'Ongi etorri — Kostan Elkartea';
	}

	/**
	 * Get the public site base URL used in outbound email links.
	 *
	 * @return string Empty string to fall back to WordPress URLs.
	 */
	private static function get_public_site_base_url() {
		$url = '';

		if ( function_exists( 'get_field' ) ) {
			$url = (string) get_field( 'komunikazioa_public_site_url', self::SETTINGS_POST_ID );
		}

		if ( '' === $url && defined( 'KOMUNIKAZIOA_PUBLIC_SITE_URL' ) ) {
			$url = (string) KOMUNIKAZIOA_PUBLIC_SITE_URL;
		}

		$url = apply_filters( 'komunikazioa_public_site_url', $url );

		if ( '' === $url ) {
			return '';
		}

		return untrailingslashit( esc_url_raw( $url ) );
	}

	/**
	 * Resolve the onboarding page URL for email links.
	 *
	 * @return string
	 */
	private static function get_onboarding_page_url() {
		$relative_path = '/ongi-etorri/';

		if ( function_exists( 'kostan_get_onboarding_url' ) ) {
			$local_url = kostan_get_onboarding_url();
			$parsed    = wp_parse_url( $local_url, PHP_URL_PATH );
			if ( is_string( $parsed ) && '' !== $parsed ) {
				$relative_path = $parsed;
			}
		}

		$public_base = self::get_public_site_base_url();
		if ( $public_base ) {
			return $public_base . $relative_path;
		}

		if ( function_exists( 'kostan_get_onboarding_url' ) ) {
			return kostan_get_onboarding_url();
		}

		return home_url( '/ongi-etorri/' );
	}

	/**
	 * Build onboarding reset URL for email CTA links.
	 *
	 * @param string $login User login.
	 * @param string $key   Reset key.
	 * @return string
	 */
	public static function get_onboarding_reset_url( $login, $key ) {
		$login = trim( (string) $login );
		$key   = trim( (string) $key );
		$base  = self::get_onboarding_page_url();

		return add_query_arg(
			array(
				'login' => $login,
				'key'   => $key,
			),
			$base
		);
	}

	/**
	 * Send onboarding welcome email to a newly imported user.
	 *
	 * @param \WP_User $user User object.
	 * @return bool
	 */
	public static function send_user_onboarding_mail( $user ) {
		if ( ! ( $user instanceof \WP_User ) ) {
			return false;
		}

		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			self::$mail_error = $key->get_error_message();
			return false;
		}

		$reset_url = self::get_onboarding_reset_url( $user->user_login, $key );
		$body      = self::build_onboarding_mail_html( $user->user_email, $reset_url, false );

		$sent = self::send_html_mail( array( $user->user_email ), self::get_onboarding_mail_subject(), $body, $user->display_name, 0 );
		if ( $sent ) {
			update_user_meta( $user->ID, 'komunikazioa_last_onboarding_mail', current_time( 'mysql' ) );
		}

		return $sent;
	}

	/**
	 * Password reset link lifetime in seconds (default 15 days).
	 *
	 * @param int $expiration Default expiration from WordPress.
	 * @return int
	 */
	public static function filter_password_reset_expiration( $expiration ) {
		return self::get_password_reset_expiration_seconds();
	}

	/**
	 * Suppress admin emails when socios set or change their password.
	 */
	public static function register_member_password_notification_filters() {
		remove_action( 'after_password_reset', 'wp_password_change_notification' );
		add_action( 'after_password_reset', array( __CLASS__, 'maybe_send_password_change_notification_to_admin' ), 10, 2 );
		add_filter( 'wp_password_change_notification_email', array( __CLASS__, 'filter_password_change_notification_email' ), 10, 3 );
	}

	/**
	 * Whether the site admin should be notified about this user's password change.
	 *
	 * @param mixed $user User object or ID.
	 * @return bool
	 */
	private static function should_suppress_password_change_admin_notice( $user ) {
		if ( function_exists( 'kostan_is_socios_user' ) ) {
			return kostan_is_socios_user( $user );
		}

		if ( ! ( $user instanceof \WP_User ) ) {
			$user = get_user_by( 'id', $user );
		}

		if ( ! ( $user instanceof \WP_User ) ) {
			return false;
		}

		$member_roles = apply_filters(
			'komunikazioa_member_password_notice_suppressed_roles',
			array( User_Importer::IMPORT_ROLE, 'socio', 'bazkide', 'bazkideak', 'subscriber' )
		);
		$member_roles = array_map( 'strtolower', (array) $member_roles );
		$user_roles   = array_map( 'strtolower', (array) $user->roles );

		return (bool) array_intersect( $member_roles, $user_roles );
	}

	/**
	 * Notify the site admin after password reset unless the user is a socio.
	 *
	 * @param \WP_User $user     User object.
	 * @param string   $new_pass New password.
	 */
	public static function maybe_send_password_change_notification_to_admin( $user, $new_pass ) {
		unset( $new_pass );

		if ( self::should_suppress_password_change_admin_notice( $user ) ) {
			return;
		}

		if ( function_exists( 'wp_password_change_notification' ) ) {
			wp_password_change_notification( $user );
		}
	}

	/**
	 * Block the admin password-change email for socios (profile updates).
	 *
	 * @param array|false $email    Email arguments.
	 * @param \WP_User    $user     User object.
	 * @param string      $blogname Site name.
	 * @return array|false
	 */
	public static function filter_password_change_notification_email( $email, $user, $blogname ) {
		unset( $blogname );

		if ( self::should_suppress_password_change_admin_notice( $user ) ) {
			return false;
		}

		return $email;
	}

	/**
	 * Password reset link lifetime in days.
	 *
	 * @return int
	 */
	public static function get_password_reset_expiration_days() {
		return (int) apply_filters( 'komunikazioa_password_reset_expiration_days', self::PASSWORD_RESET_EXPIRATION_DAYS );
	}

	/**
	 * Password reset link lifetime in seconds.
	 *
	 * @return int
	 */
	public static function get_password_reset_expiration_seconds() {
		return self::get_password_reset_expiration_days() * DAY_IN_SECONDS;
	}

	/**
	 * Bilingual expiry notice for onboarding emails.
	 *
	 * @return string
	 */
	public static function get_password_reset_expiration_mail_text() {
		$days = self::get_password_reset_expiration_days();

		return sprintf(
			'Esteka hau %1$d egunetan iraungiko da. / Este enlace caducará en %1$d días.',
			$days
		);
	}

	/**
	 * Whether the user completed onboarding (accepted terms / set password).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_user_onboarding_complete( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}

		return '1' === (string) get_user_meta( $user_id, 'kostan_terms_accepted', true );
	}

	/**
	 * Human-readable onboarding status for admin tables.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_user_onboarding_status_label( $user_id ) {
		if ( self::is_user_onboarding_complete( $user_id ) ) {
			return self::admin_label( 'Activo', 'Aktiboa' );
		}

		return self::admin_label( 'Pendiente', 'Zain' );
	}

	/**
	 * Remember an imported user for follow-up in the admin UI.
	 *
	 * @param \WP_User $user User object.
	 */
	public static function add_to_import_roster( $user ) {
		if ( ! ( $user instanceof \WP_User ) ) {
			return;
		}

		$roster = get_option( self::IMPORT_ROSTER_OPTION, array() );
		if ( ! is_array( $roster ) ) {
			$roster = array();
		}

		$user_id = (int) $user->ID;
		$roster[ $user_id ] = array(
			'user_id'    => $user_id,
			'email'      => (string) $user->user_email,
			'login'      => (string) $user->user_login,
			'updated_at' => current_time( 'mysql' ),
		);

		if ( count( $roster ) > 500 ) {
			uasort(
				$roster,
				static function ( $a, $b ) {
					return strcmp( (string) $a['updated_at'], (string) $b['updated_at'] );
				}
			);
			$roster = array_slice( $roster, -500, null, true );
		}

		update_option( self::IMPORT_ROSTER_OPTION, $roster, false );
	}

	/**
	 * Get persisted import roster entries keyed by user ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_import_roster() {
		$roster = get_option( self::IMPORT_ROSTER_OPTION, array() );

		return is_array( $roster ) ? $roster : array();
	}

	/**
	 * Send onboarding template to a test address using the current admin account key.
	 *
	 * @param string $email Recipient email.
	 * @return bool
	 */
	private static function send_onboarding_test_mail( $email ) {
		$admin = wp_get_current_user();
		if ( ! ( $admin instanceof \WP_User ) || ! $admin->exists() ) {
			self::$mail_error = self::admin_label( 'No se pudo obtener el usuario administrador.', 'Ezin izan da administratzaile erabiltzailea lortu.' );
			return false;
		}

		$key = get_password_reset_key( $admin );
		if ( is_wp_error( $key ) ) {
			self::$mail_error = $key->get_error_message();
			return false;
		}

		$reset_url = self::get_onboarding_reset_url( $admin->user_login, $key );
		$body      = self::build_onboarding_mail_html( $email, $reset_url, true );

		return self::send_html_mail( array( $email ), self::get_onboarding_mail_subject() . ' [TEST]', $body, '', 0 );
	}

	/**
	 * Get the latest mail error message.
	 *
	 * @return string
	 */
	public static function get_last_mail_error_message() {
		if ( self::$mail_error ) {
			return (string) self::$mail_error;
		}

		return self::admin_label( 'Error desconocido al enviar el correo.', 'Errore ezezaguna mezua bidaltzean.' );
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
			echo '<div class="komunikazioa-form__name-row">';
			echo '<p class="komunikazioa-form__field">';
			echo '<label class="komunikazioa-form__label" for="' . esc_attr( $form_id ) . '-first-name">' . esc_html__( 'Izena', 'komunikazioa' ) . '</label>';
			echo '<input class="komunikazioa-form__input" id="' . esc_attr( $form_id ) . '-first-name" required type="text" name="komunikazioa_first_name" autocomplete="given-name" />';
			echo '</p>';
			echo '<p class="komunikazioa-form__field">';
			echo '<label class="komunikazioa-form__label" for="' . esc_attr( $form_id ) . '-last-name">' . esc_html__( 'Abizenak', 'komunikazioa' ) . '</label>';
			echo '<input class="komunikazioa-form__input" id="' . esc_attr( $form_id ) . '-last-name" required type="text" name="komunikazioa_last_name" autocomplete="family-name" />';
			echo '</p>';
			echo '</div>';
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
			echo '<label class="komunikazioa-form__label" for="' . esc_attr( $form_id ) . '-city">' . esc_html__( 'Herria', 'komunikazioa' ) . '</label>';
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
