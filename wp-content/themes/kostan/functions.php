<?php
if (!defined('ABSPATH')) exit;

define('ct_THEME_VERSION', wp_get_theme()->get('Version'));

require_once get_template_directory() . '/inc/icons.php';
require_once get_template_directory() . '/acf-fields/hero-carousel.php';

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
    }
}
add_action('acf/init', 'ct_acf_init');

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
 *   - vowel-ending     → genitive '-ko',  locative '-n'
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
 * Return the Basque month name in genitive case.
 * Standard WP/WPML Basque month names end in -a; genitive replaces -a with -aren.
 * e.g. "Maiatza" → "Maiatzaren", "Ekaina" → "Ekainaren".
 */
function kostan_eu_month_genitive( $timestamp ) {
    $month = wp_date( 'F', $timestamp );
    if ( substr( $month, -1 ) === 'a' ) {
        return substr( $month, 0, -1 ) . 'aren';
    }
    // Fallback for non-standard locale month names
    return $month . 'ren';
}

/**
 * Format a timestamp with a localized date format.
 * Basque dates are assembled dynamically to respect morphophonological rules.
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
                $month_gen = kostan_eu_month_genitive( $timestamp );
                $day       = (int) wp_date( 'j', $timestamp );
                $day_loc   = ( kostan_eu_number_vowel_type( $day ) === 'eko' )
                    ? "{$day}ean"
                    : "{$day}n";
                return "{$year}{$suffix} {$month_gen} {$day_loc}";
        }
    }

    return wp_date( kostan_get_localized_date_format( $context ), $timestamp );
}

