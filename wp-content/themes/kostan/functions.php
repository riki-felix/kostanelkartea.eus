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

