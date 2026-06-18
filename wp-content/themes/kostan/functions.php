<?php
if (!defined('ABSPATH')) exit;

define('ct_THEME_VERSION', wp_get_theme()->get('Version'));

require_once get_template_directory() . '/inc/icons.php';
require_once get_template_directory() . '/acf-fields/hero-carousel.php';
require_once get_template_directory() . '/acf-fields/deskargak.php';

/**
 * Render session indicator (login/logout).
 * Shows when user is logged in; empty when logged out.
 * 
 * @param bool $mobile Whether this is for mobile menu (true) or desktop (false).
 */
function ct_render_session_indicator( $mobile = false ) {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$current_user = wp_get_current_user();
	$user_name    = $current_user->display_name ?: $current_user->user_login;
	$logout_url   = wp_logout_url( home_url() );

	if ( $mobile ) {
        // Mobile: same alert style inside menu panel
		?>
		<li class="user-session user-session--mobile">
            <div class="user-session__alert">
                <span class="user-session__icon" aria-hidden="true"><?php kostan_the_icon( 'lock', 16 ); ?></span>
                <span class="user-session__greeting"><?php echo esc_html( sprintf( __( 'Kaixo %s!', 'kostan' ), $user_name ) ); ?></span>
                <a href="<?php echo esc_url( $logout_url ); ?>" class="user-session__logout">
                    <span class="user-session__logout-text"><?php esc_html_e( 'Itxi sesioa', 'kostan' ); ?></span>
                    <span class="user-session__logout-icon" aria-hidden="true"><?php kostan_the_icon( 'arrow-right', 16 ); ?></span>
                </a>
            </div>
		</li>
		<?php
	} else {
        // Desktop: alert row below main header bar
		?>
		<div class="user-session user-session--desktop">
			<div class="user-session__alert">
                <span class="user-session__icon" aria-hidden="true"><?php kostan_the_icon( 'lock', 16 ); ?></span>
				<span class="user-session__greeting"><?php echo esc_html( sprintf( __( 'Kaixo %s!', 'kostan' ), $user_name ) ); ?></span>
                <a href="<?php echo esc_url( $logout_url ); ?>" class="user-session__logout">
                    <span class="user-session__logout-text"><?php esc_html_e( 'Itxi sesioa', 'kostan' ); ?></span>
                    <span class="user-session__logout-icon" aria-hidden="true"><?php kostan_the_icon( 'arrow-right', 16 ); ?></span>
                </a>
			</div>
		</div>
		<?php
	}
}

/**
 * Enqueue assets - Direct output method
 */
function ct_enqueue_assets() {
    $theme_uri = get_template_directory_uri();
    $version = ct_THEME_VERSION;
    
    // Output CSS directly in head
    add_action('wp_head', function() use ($theme_uri, $version) {
        echo '<link rel="stylesheet" id="vh-style-css" href="' . esc_url($theme_uri . '/dist/css/main.css?ver=' . $version) . '" type="text/css" media="all" />' . "\n";
    }, 10);
    
    // Output JS directly in footer
    add_action('wp_footer', function() use ($theme_uri, $version) {
        echo '<script id="vh-script-js" src="' . esc_url($theme_uri . '/dist/js/main.js?ver=' . $version) . '"></script>' . "\n";
    }, 10);
}
add_action('wp_enqueue_scripts', 'ct_enqueue_assets');

/**
 * Debug helper
 */
function ct_debug_assets() {
    if (isset($_GET['debug_assets']) && current_user_can('manage_options')) {
        $theme_dir = get_template_directory();
        $theme_uri = get_template_directory_uri();
        
        echo '<div style="position:fixed;top:10px;right:10px;background:#000;color:#0f0;padding:20px;z-index:99999;font-family:monospace;border:3px solid #0f0;max-width:500px;font-size:12px;line-height:1.6;">';
        echo '<strong style="font-size:14px;">🔍 Asset Debug Info</strong><br><br>';
        
        $js_file = $theme_dir . '/dist/js/main.js';
        $css_file = $theme_dir . '/dist/css/main.css';
        
        echo '<strong>JS File:</strong><br>';
        echo file_exists($js_file) ? '✅ EXISTS' : '❌ NOT FOUND';
        echo '<br>' . esc_html($js_file) . '<br>';
        if (file_exists($js_file)) {
            echo 'Size: ' . filesize($js_file) . ' bytes<br>';
        }
        echo '<br>';
        
        echo '<strong>CSS File:</strong><br>';
        echo file_exists($css_file) ? '✅ EXISTS' : '❌ NOT FOUND';
        echo '<br>' . esc_html($css_file) . '<br>';
        if (file_exists($css_file)) {
            echo 'Size: ' . filesize($css_file) . ' bytes<br>';
        }
        
        echo '</div>';
    }
}
add_action('wp_footer', 'ct_debug_assets', 999);

/**
 * Theme setup
 */
function ct_setup() {
	load_theme_textdomain( 'kostan', get_template_directory() . '/languages' );
	
    add_theme_support('automatic-feed-links');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    
    register_nav_menus([
        'primary' => __('Primary Menu', 'kostan'),
        'actions' => __('Actions Menu', 'kostan'),
        'footer'  => __('Footer Menu', 'kostan'),
    ]);
    
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);
    
    add_theme_support('customize-selective-refresh-widgets');
    
    add_theme_support('custom-logo', [
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
}
add_action('after_setup_theme', 'ct_setup');

/**
 * Register widget areas
 */
function ct_widgets_init() {
    $footer_sidebars = [
        'footer-1' => __('Footer 1', 'kostan'),
    ];
    
    foreach ($footer_sidebars as $id => $name) {
        register_sidebar([
            'name'          => $name,
            'id'            => $id,
            'description'   => __('Footer Widgets', 'kostan'),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h4 class="widget-title">',
            'after_title'   => '</h4>',
        ]);
    }
}
add_action('widgets_init', 'ct_widgets_init');

/**
 * Custom excerpt length
 */
function ct_excerpt_length($length) {
    return 30;
}
add_filter('excerpt_length', 'ct_excerpt_length', 999);

/**
 * Custom excerpt more
 */
function ct_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'ct_excerpt_more');

/**
 * Allow SVG uploads (admins only)
 */
function ct_allow_svg_uploads($mimes) {
    if (current_user_can('manage_options')) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
    }
    return $mimes;
}
add_filter('upload_mimes', 'ct_allow_svg_uploads');

/**
 * Fix SVG MIME type detection
 */
function ct_fix_svg_mime_type($data, $file, $filename, $mimes, $real_mime = null) {
    if (pathinfo($filename, PATHINFO_EXTENSION) === 'svg') {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'ct_fix_svg_mime_type', 10, 5);

/**
 * Disable WPML language selector CSS
 */
define('ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS', true);

/**
 * Rename "Entradas" to "Actividades"
 */
function ct_rename_posts_to_actividades() {
    global $wp_post_types;

    $labels = &$wp_post_types['post']->labels;
    $labels->name               = 'Actividades';
    $labels->singular_name      = 'Actividad';
    $labels->add_new            = 'Añadir nueva';
    $labels->add_new_item       = 'Añadir nueva actividad';
    $labels->edit_item          = 'Editar actividad';
    $labels->new_item           = 'Nueva actividad';
    $labels->view_item          = 'Ver actividad';
    $labels->search_items       = 'Buscar actividades';
    $labels->not_found          = 'No se encontraron actividades';
    $labels->not_found_in_trash = 'No se encontraron actividades en la papelera';
    $labels->all_items          = 'Todas las actividades';
    $labels->menu_name          = 'Actividades';
    $labels->name_admin_bar     = 'Actividad';
}
add_action('init', 'ct_rename_posts_to_actividades');

/**
 * ACF Google Maps API key + ACF Blocks
 */
function ct_acf_init() {
    if ( defined('GOOGLE_MAPS_API_KEY') ) {
        acf_update_setting('google_api_key', GOOGLE_MAPS_API_KEY);
    }

    if ( function_exists('acf_register_block_type') ) {
        acf_register_block_type([
            'name'            => 'hero-carousel',
            'title'           => __('Carrusel Hero', 'kostan'),
            'description'     => __('Carrusel de imagenes para la portada.', 'kostan'),
            'render_template' => 'template-parts/blocks/hero-carousel.php',
            'category'        => 'formatting',
            'icon'            => 'images-alt2',
            'keywords'        => ['hero', 'carousel', 'carrusel', 'slides'],
            'supports'        => ['align' => ['full'], 'multiple' => false],
        ]);

        acf_register_block_type([
            'name'            => 'venue-info',
            'title'           => __('Recintos', 'kostan'),
            'description'     => __('Lista los recintos (venue) con su foto, dirección y enlace a Google Maps.', 'kostan'),
            'render_template' => 'template-parts/blocks/venue-info.php',
            'category'        => 'formatting',
            'icon'            => 'location-alt',
            'keywords'        => ['venue', 'recinto', 'mapa', 'recintos'],
            'supports'        => ['align' => false, 'multiple' => true],
        ]);

        acf_register_block_type([
            'name'            => 'monthly-talks',
            'title'           => __('Ponencias del mes', 'kostan'),
            'description'     => __('Muestra las ponencias del mes actual en un grid.', 'kostan'),
            'render_template' => 'template-parts/blocks/monthly-talks.php',
            'category'        => 'formatting',
            'icon'            => 'calendar-alt',
            'keywords'        => ['talks', 'ponencias', 'mes', 'monthly'],
            'supports'        => ['align' => false, 'multiple' => false],
        ]);

        acf_register_block_type([
            'name'            => 'latest-news',
            'title'           => __('Últimas noticias', 'kostan'),
            'description'     => __('Slider con las últimas 5 noticias.', 'kostan'),
            'render_template' => 'template-parts/blocks/latest-news.php',
            'category'        => 'formatting',
            'icon'            => 'megaphone',
            'keywords'        => ['news', 'noticias', 'slider', 'últimas'],
            'supports'        => ['align' => false, 'multiple' => false],
        ]);

        acf_register_block_type([
            'name'            => 'deskargak',
            'title'           => __('Deskargak', 'kostan'),
            'description'     => __('Contenedor de descargas con items anidados.', 'kostan'),
            'render_template' => 'template-parts/blocks/deskargak.php',
            'mode'            => 'preview',
            'category'        => 'formatting',
            'icon'            => 'download',
            'keywords'        => ['deskargak', 'descargas', 'archivos', 'downloads'],
            'supports'        => ['align' => ['wide'], 'multiple' => true, 'jsx' => true],
        ]);

        acf_register_block_type([
            'name'            => 'deskargak-item',
            'title'           => __('Deskargak Item', 'kostan'),
            'description'     => __('Elemento individual de descarga.', 'kostan'),
            'render_template' => 'template-parts/blocks/deskargak-item.php',
            'mode'            => 'preview',
            'category'        => 'formatting',
            'icon'            => 'media-document',
            'keywords'        => ['deskargak', 'descarga', 'archivo', 'item'],
            'parent'          => ['acf/deskargak'],
            'supports'        => ['align' => false, 'multiple' => true],
        ]);
    }
}
add_action('acf/init', 'ct_acf_init');

/**
 * Add body classes useful for header/session layout.
 *
 * @param array $classes Body classes.
 * @return array
 */
function kostan_body_classes( $classes ) {
    if ( is_user_logged_in() ) {
        $classes[] = 'has-user-session';
    }

    return $classes;
}
add_filter( 'body_class', 'kostan_body_classes' );

/**
 * Enqueue Google Maps API on frontend when needed
 */
function ct_enqueue_google_maps() {
    if ( ! is_singular() && ! is_page_template() ) {
        return;
    }
    wp_enqueue_script(
        'google-maps',
        'https://maps.googleapis.com/maps/api/js?key=' . ( defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '' ),
        [],
        null,
        true
    );
}
add_action('wp_enqueue_scripts', 'ct_enqueue_google_maps');

/**
 * Restrict frontend search results to activities (post) and news.
 */
function kostan_limit_search_post_types( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
        return;
    }

    $query->set( 'post_type', [ 'post', 'news' ] );
}
add_action( 'pre_get_posts', 'kostan_limit_search_post_types' );

/**
 * Search UI labels by current language.
 */
function kostan_search_ui_strings() {
    $lang = kostan_get_current_language_code();

    if ( 'eu' === $lang ) {
        return [
            'label'             => 'Bilatu',
            'button'            => 'Bilatu',
            'placeholder'       => 'Bilatu jarduerak eta berriak',
            'results_title'     => 'Bilaketa emaitzak',
            'results_summary'   => '%1$d emaitza "%2$s" bilaketarako',
            'no_results'        => 'Ez da emaitzarik aurkitu.',
            'type_news'         => 'Berria',
            'type_activity'     => 'Jarduera',
            'no_category'       => 'Kategoriarik gabe',
        ];
    }

    return [
        'label'             => 'Buscar',
        'button'            => 'Buscar',
        'placeholder'       => 'Buscar actividades y noticias',
        'results_title'     => 'Resultados de busqueda',
        'results_summary'   => '%1$d resultados para "%2$s"',
        'no_results'        => 'No se han encontrado resultados.',
        'type_news'         => 'Noticia',
        'type_activity'     => 'Actividad',
        'no_category'       => 'Sin categoria',
    ];
}

/**
 * Render the automatic category menu used on Ekintzak pages.
 */
function kostan_render_activity_categories_menu() {
    $term_args = [
        'taxonomy'   => 'category',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ];

    $current_lang = kostan_get_current_language_code();
    if ( has_filter( 'wpml_current_language' ) && ! empty( $current_lang ) ) {
        $term_args['lang'] = $current_lang;
    }

    $terms = get_terms( $term_args );
    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return '';
    }

    $current_term_id = 0;
    if ( is_category() ) {
        $current_term_id = (int) get_queried_object_id();
    }

    $items = [];
    foreach ( $terms as $term ) {
        $link = get_term_link( $term );
        if ( is_wp_error( $link ) ) {
            continue;
        }

        $is_active = $current_term_id && (int) $term->term_id === $current_term_id;
        $items[]   = sprintf(
            '<li class="page-ekintzak__topic-item%1$s"><a href="%2$s">%3$s</a></li>',
            $is_active ? ' is-active' : '',
            esc_url( $link ),
            esc_html( $term->name )
        );
    }

    if ( empty( $items ) ) {
        return '';
    }

    $label = 'eu' === $current_lang ? 'Gaiak' : 'Temas';

    return sprintf(
        '<nav class="page-ekintzak__topics" aria-label="%1$s"><span class="page-ekintzak__topics-label">%2$s</span><ul class="page-ekintzak__topics-list">%3$s</ul></nav>',
        esc_attr( $label ),
        esc_html( $label ),
        implode( '', $items )
    );
}

/**
 * Get an ACF field from a taxonomy term.
 * Since symbol/color are shared across languages, always read from the
 * default-language term to avoid sync issues with WPML + ACF copy.
 */
function kostan_get_area_field( $field_name, $term_id, $taxonomy = 'area' ) {
	$default_lang    = apply_filters( 'wpml_default_language', null );
	$original_id     = apply_filters( 'wpml_object_id', $term_id, $taxonomy, true, $default_lang );
	$value = get_field( $field_name, $taxonomy . '_' . ( $original_id ?: $term_id ) );
	if ( $value ) {
		return $value;
	}
	// If wpml_object_id returned the same id, try the passed one as last resort
	if ( $original_id && $original_id !== (int) $term_id ) {
		$value = get_field( $field_name, $taxonomy . '_' . $term_id );
	}
	return $value;
}

/**
 * Get area terms for a post, falling back to the default-language post's terms
 * when WPML is active and the translated post has no area associations.
 * Returns translated term objects for display (names/links in current language).
 */
function kostan_get_post_areas( $post_id ) {
	$terms = get_the_terms( $post_id, 'area' );
	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		return $terms;
	}
	// Fallback: get terms from the default-language post
	$default_lang     = apply_filters( 'wpml_default_language', null );
	$current_lang     = apply_filters( 'wpml_current_language', null );
	$original_post_id = apply_filters( 'wpml_object_id', $post_id, get_post_type( $post_id ), true, $default_lang );
	if ( ! $original_post_id || $original_post_id === (int) $post_id ) {
		return [];
	}
	// Switch to default language so WPML doesn't filter out untranslated terms
	do_action( 'wpml_switch_language', $default_lang );
	$original_terms = get_the_terms( $original_post_id, 'area' );
	do_action( 'wpml_switch_language', $current_lang );

	if ( empty( $original_terms ) || is_wp_error( $original_terms ) ) {
		return [];
	}
	// Map each term back to the current language where a translation exists
	$result = [];
	foreach ( $original_terms as $t ) {
		$translated_id   = apply_filters( 'wpml_object_id', $t->term_id, 'area', true, $current_lang );
		$translated_term = get_term( $translated_id, 'area' );
		if ( $translated_term && ! is_wp_error( $translated_term ) ) {
			$result[] = $translated_term;
		}
	}
	return ! empty( $result ) ? $result : $original_terms;
}

/**
 * Highlight the parent menu item for CPT singles, archives, and taxonomy pages.
 */
function kostan_nav_menu_highlight_parent( $classes, $menu_item ) {
	// Talks, venues, areas → highlight Ponentziak page
	if ( is_singular( 'talks' ) || is_post_type_archive( 'talks' ) || is_tax( 'venue' ) || is_tax( 'area' ) ) {
		$classes = array_diff( $classes, [ 'current_page_parent', 'current-menu-item' ] );

		if ( $menu_item->object === 'page' ) {
			$tpl = get_page_template_slug( $menu_item->object_id );
			if ( $tpl === 'page-ponentziak.php' ) {
				$classes[] = 'current-menu-item';
			}
		}
	}

	// Blog posts → highlight the posts page menu item (Ekintzak)
	if ( is_singular( 'post' ) ) {
		$classes = array_diff( $classes, [ 'current_page_parent', 'current-menu-item' ] );

		$blog_page_id = (int) get_option( 'page_for_posts' );
		if ( $blog_page_id && $menu_item->object === 'page' ) {
			// Match the blog page or any of its WPML translations
			$target_id = (int) $menu_item->object_id;
			if ( $target_id === $blog_page_id ) {
				$classes[] = 'current-menu-item';
			} elseif ( function_exists( 'icl_object_id' ) ) {
				$translated_blog = (int) icl_object_id( $blog_page_id, 'page', false );
				if ( $translated_blog && $target_id === $translated_blog ) {
					$classes[] = 'current-menu-item';
				}
			}
		}
	}

	return $classes;
}
add_filter( 'nav_menu_css_class', 'kostan_nav_menu_highlight_parent', 10, 2 );

/**
 * Add page-context body classes for shared templates.
 *
 * These classes are language-agnostic and safe with WPML.
 */
function kostan_body_context_classes( $classes ) {
    if ( is_page_template( 'page-noticias.php' ) ) {
        $classes[] = 'ctx-berriak';
    }

    return $classes;
}
add_filter( 'body_class', 'kostan_body_context_classes' );

/**
 * Get onboarding welcome page URL.
 */
function kostan_get_onboarding_url( $lang = '' ) {
    $page = get_page_by_path( 'ongi-etorri' );

    if ( $page ) {
        $page_id = (int) $page->ID;
        $lang    = strtolower( (string) $lang );

        if ( $lang && has_filter( 'wpml_object_id' ) ) {
            $translated = apply_filters( 'wpml_object_id', $page_id, 'page', true, $lang );
            if ( $translated ) {
                $page_id = (int) $translated;
            }
        }

        return get_permalink( $page_id );
    }

    return home_url( '/ongi-etorri/' );
}

/**
 * Onboarding destination after password change.
 */
function kostan_get_members_area_url() {
    return home_url( '/bazkideak/' );
}

/**
 * Get frontend lost-password page URL.
 */
function kostan_get_lost_password_page_url( $lang = '' ) {
    $page = get_page_by_path( 'pasahitza-berreskuratu' );

    if ( ! $page ) {
        $page = get_page_by_path( 'recuperar-contrasena' );
    }

    if ( $page ) {
        $page_id = (int) $page->ID;
        $lang    = strtolower( (string) $lang );

        if ( $lang && has_filter( 'wpml_object_id' ) ) {
            $translated = apply_filters( 'wpml_object_id', $page_id, 'page', true, $lang );
            if ( $translated ) {
                $page_id = (int) $translated;
            }
        }

        return get_permalink( $page_id );
    }

    return home_url( '/pasahitza-berreskuratu/' );
}

/**
 * Check if a user belongs to the Socios/member role set.
 */
function kostan_is_socios_user( $user = null ) {
    if ( ! $user ) {
        $user = wp_get_current_user();
    }

    if ( ! ( $user instanceof WP_User ) || empty( $user->roles ) ) {
        return false;
    }

    $blocked_roles = apply_filters( 'kostan_socios_roles', [ 'socios', 'socio', 'bazkide', 'bazkideak', 'subscriber' ] );
    $blocked_roles = array_map( 'strtolower', (array) $blocked_roles );
    $user_roles    = array_map( 'strtolower', (array) $user->roles );

    return (bool) array_intersect( $blocked_roles, $user_roles );
}

/**
 * Determine if user should stay in frontend only.
 */
function kostan_is_frontend_only_user( $user = null ) {
    if ( ! $user ) {
        $user = wp_get_current_user();
    }

    if ( ! ( $user instanceof WP_User ) ) {
        return false;
    }

    if ( current_user_can( 'manage_options' ) ) {
        return false;
    }

    if ( current_user_can( 'edit_posts' ) ) {
        return false;
    }

    return kostan_is_socios_user( $user ) || current_user_can( 'read' );
}

/**
 * Redirect Socios users away from wp-admin while keeping ajax endpoints available.
 */
function kostan_block_socios_admin_access() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    if ( wp_doing_ajax() || wp_doing_cron() ) {
        return;
    }

    if ( ! is_admin() ) {
        return;
    }

    if ( ! kostan_is_frontend_only_user() ) {
        return;
    }

    wp_safe_redirect( kostan_get_members_area_url() );
    exit;
}
add_action( 'admin_init', 'kostan_block_socios_admin_access' );

/**
 * Send Socios users to members area after login.
 */
function kostan_socios_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
    if ( $user instanceof WP_User && kostan_is_frontend_only_user( $user ) ) {
        return kostan_get_members_area_url();
    }

    return $redirect_to;
}
add_filter( 'login_redirect', 'kostan_socios_login_redirect', 10, 3 );

/**
 * Hide admin bar on frontend for Socios users.
 */
function kostan_hide_admin_bar_for_socios( $show ) {
    if ( is_user_logged_in() && kostan_is_frontend_only_user() ) {
        return false;
    }

    return $show;
}
add_filter( 'show_admin_bar', 'kostan_hide_admin_bar_for_socios' );

/**
 * Force-hide the admin bar early for frontend-only users.
 */
function kostan_force_hide_admin_bar_for_socios() {
    if ( is_user_logged_in() && kostan_is_frontend_only_user() ) {
        show_admin_bar( false );
    }
}
add_action( 'after_setup_theme', 'kostan_force_hide_admin_bar_for_socios' );

/**
 * Add "lost password" link to Gutenberg Login/Logout block login form.
 */
function kostan_loginout_add_lost_password_link( $block_content, $block ) {
    if ( is_admin() || is_user_logged_in() ) {
        return $block_content;
    }

    $block_name = $block['blockName'] ?? '';
    $is_loginout_block = 'core/loginout' === $block_name || false !== strpos( $block_content, 'wp-block-loginout' );

    if ( ! $is_loginout_block ) {
        return $block_content;
    }

    if ( false !== strpos( $block_content, 'kostan-login-lost-password' ) ) {
        return $block_content;
    }

    $lost_password_url = kostan_get_lost_password_page_url();
    $lang              = kostan_get_current_language_code();
    if ( $lang ) {
        $lost_password_url = add_query_arg( 'wp_lang', $lang, $lost_password_url );
    }
    $link_html         = sprintf(
        '<p class="kostan-login-lost-password"><a href="%1$s">%2$s</a></p>',
        esc_url( $lost_password_url ),
        esc_html__( 'Ezin duzu pasahitza gogoratzen? / Has olvidado tu contrasena?', 'kostan' )
    );

    return $block_content . $link_html;
}
add_filter( 'render_block', 'kostan_loginout_add_lost_password_link', 10, 2 );

/**
 * Redirect wp-login lost-password actions to frontend recovery page.
 */
function kostan_redirect_lostpassword_to_frontend() {
    $lang = isset( $_REQUEST['wp_lang'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wp_lang'] ) ) : '';

    wp_safe_redirect( kostan_get_lost_password_page_url( $lang ) );
    exit;
}
add_action( 'login_form_lostpassword', 'kostan_redirect_lostpassword_to_frontend' );
add_action( 'login_form_retrievepassword', 'kostan_redirect_lostpassword_to_frontend' );

/**
 * Avoid caching the front page for guest users.
 * Mitigates blank-page incidents caused by poisoned edge/full-page cache entries.
 */
function kostan_disable_guest_cache_on_front_page() {
    if ( is_admin() || is_user_logged_in() || ! is_front_page() ) {
        return;
    }

    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }

    nocache_headers();
}
add_action( 'template_redirect', 'kostan_disable_guest_cache_on_front_page', 0 );

/**
 * Redirect WP reset-password links to onboarding page.
 */
function kostan_redirect_reset_to_onboarding() {
    $login = isset( $_REQUEST['login'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['login'] ) ) : '';
    $key   = isset( $_REQUEST['key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) : '';
    $lang  = isset( $_REQUEST['wp_lang'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wp_lang'] ) ) : '';

    if ( '' === $login || '' === $key ) {
        return;
    }

    $onboarding_url = add_query_arg(
        [
            'login' => $login,
            'key'   => $key,
        ],
        kostan_get_onboarding_url( $lang )
    );

    if ( $lang ) {
        $onboarding_url = add_query_arg( 'wp_lang', strtolower( $lang ), $onboarding_url );
    }

    wp_safe_redirect( $onboarding_url );
    exit;
}
add_action( 'login_form_rp', 'kostan_redirect_reset_to_onboarding' );
add_action( 'login_form_resetpass', 'kostan_redirect_reset_to_onboarding' );

/**
 * Get current language code (WPML-aware).
 */
function kostan_get_current_language_code() {
    if ( has_filter( 'wpml_current_language' ) ) {
        $lang = apply_filters( 'wpml_current_language', null );
        if ( ! empty( $lang ) ) {
            return strtolower( (string) $lang );
        }
    }

    $locale = determine_locale();
    return strtolower( substr( (string) $locale, 0, 2 ) );
}

/**
 * Return a wp_date() format string for non-Basque languages.
 * Basque dates are assembled dynamically in kostan_format_timestamp().
 */
function kostan_get_localized_date_format( $context = 'date' ) {
    $lang = kostan_get_current_language_code();

    if ( $lang === 'es' ) {
        switch ( $context ) {
            case 'month':
                return 'F';
            case 'month_year':
                return 'F Y';
            case 'date':
            default:
                return 'j \\d\\e F \\d\\e Y';
        }
    }

    switch ( $context ) {
        case 'month':
            return 'F';
        case 'month_year':
            return 'F Y';
        case 'date':
        default:
            return get_option( 'date_format' );
    }
}

/**
 * Determine whether a Basque number ends in a consonant or vowel sound.
 *
 * Basque morphophonology rules for numbers (relevant for case suffixes):
 *   - consonant-ending → genitive '-eko', locative '-ean'
 *   - vowel-ending     → genitive '-ko',  locative '-an'
 *
 * Consonant-ending cases:
 *   units = 5 → "bost" (t)          — always, regardless of tens
 *   units = 1, even tens → "bat" (t) — e.g. 1, 21, 41, 61, 81
 *   units = 0, odd  tens → "hamar" (r) — e.g. 10, 30, 50, 70, 90
 *   last2 = 0 and NOT a round-thousand → "ehun" (n) — e.g. 100, 2100
 *
 * Returns 'eko' (consonant) or 'ko' (vowel).
 */
function kostan_eu_number_vowel_type( $n ) {
    $n     = (int) $n;
    $last2 = $n % 100;

    if ( $last2 === 0 ) {
        // Round thousands → "mila" ends in 'a' (vowel) → ko
        // Round hundreds  → "ehun" ends in 'n' (consonant) → eko
        return ( $n % 1000 === 0 ) ? 'ko' : 'eko';
    }

    $units = $last2 % 10;
    $tens  = (int) ( $last2 / 10 );

    if ( $units === 5 ) {
        return 'eko'; // "bost" → t
    }

    if ( $units === 1 ) {
        // even tens (0,2,4,6,8): "bat" → t → eko  e.g. 01, 21, 41, 61, 81
        // odd  tens (1,3,5,7,9): "hamaika" → a → ko  e.g. 11, 31, 51, 71, 91
        return ( $tens % 2 === 0 ) ? 'eko' : 'ko';
    }

    if ( $units === 0 ) {
        // odd  tens (1,3,5,7,9): "hamar" → r → eko  e.g. 10, 30, 50, 70, 90
        // even tens (2,4,6,8):   "hogei/berrogei…" → i → ko  e.g. 20, 40, 60, 80
        return ( $tens % 2 === 1 ) ? 'eko' : 'ko';
    }

    // 2→bi(i), 3→hiru(u), 4→lau(u), 6→sei(i), 7→zazpi(i), 8→zortzi(i), 9→bederatzi(i)
    return 'ko';
}

/**
 * Return the Basque month name in ergative case.
 * Standard WP/WPML Basque month names end in -a; ergative appends -k.
 * e.g. "Martxoa" → "Martxoak", "Maiatza" → "Maiatzak".
 */
function kostan_eu_month_ergative( $timestamp ) {
    $month = wp_date( 'F', $timestamp );
    if ( substr( $month, -1 ) === 'a' ) {
        return $month . 'k';
    }
    // Fallback for non-standard locale month names
    return $month . 'k';
}

/**
 * Format a timestamp with a localized date format.
 * Basque dates are assembled dynamically to respect morphophonological rules.
 * Format: "2024ko martxoak 21"
 */
function kostan_format_timestamp( $timestamp, $context = 'date' ) {
    $timestamp = (int) $timestamp;
    if ( $timestamp <= 0 ) {
        return '';
    }

    if ( kostan_get_current_language_code() === 'eu' ) {
        $month = wp_date( 'F', $timestamp ); // translated by WP/WPML to Basque

        switch ( $context ) {
            case 'month':
                return $month;

            case 'month_year':
                $year   = (int) wp_date( 'Y', $timestamp );
                $suffix = kostan_eu_number_vowel_type( $year );
                return "{$year}{$suffix} {$month}";

            case 'date':
            default:
                $year      = (int) wp_date( 'Y', $timestamp );
                $suffix    = kostan_eu_number_vowel_type( $year );
                $month_erg = kostan_eu_month_ergative( $timestamp );
                $day       = (int) wp_date( 'j', $timestamp );
                return "{$year}{$suffix} {$month_erg} {$day}";
        }
    }

    return wp_date( kostan_get_localized_date_format( $context ), $timestamp );
}

