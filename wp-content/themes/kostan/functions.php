<?php
if (!defined('ABSPATH')) exit;

define('VH_THEME_VERSION', wp_get_theme()->get('Version'));

/**
 * Enqueue assets - Direct output method
 */
function vh_enqueue_assets() {
    $theme_uri = get_template_directory_uri();
    $version = time(); // Cache busting
    
    // Output CSS directly in head
    add_action('wp_head', function() use ($theme_uri, $version) {
        echo '<link rel="stylesheet" id="vh-style-css" href="' . esc_url($theme_uri . '/dist/css/main.css?ver=' . $version) . '" type="text/css" media="all" />' . "\n";
    }, 10);
    
    // Output JS directly in footer
    add_action('wp_footer', function() use ($theme_uri, $version) {
        echo '<script id="vh-script-js" src="' . esc_url($theme_uri . '/dist/js/main.js?ver=' . $version) . '"></script>' . "\n";
    }, 10);
}
add_action('init', 'vh_enqueue_assets');

/**
 * Debug helper
 */
function vh_debug_assets() {
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
add_action('wp_footer', 'vh_debug_assets', 999);

/**
 * Theme setup
 */
function vh_setup() {
    add_theme_support('automatic-feed-links');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    
    register_nav_menus([
        'primary' => __('Primary Menu', 'vh'),
        'footer'  => __('Footer Menu', 'vh'),
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
add_action('after_setup_theme', 'vh_setup');

/**
 * Register widget areas
 */
function vh_widgets_init() {
    $footer_sidebars = [
        'footer-1' => __('Footer 1', 'vh'),
        'footer-2' => __('Footer 2', 'vh'),
        'footer-3' => __('Footer 3', 'vh'),
    ];
    
    foreach ($footer_sidebars as $id => $name) {
        register_sidebar([
            'name'          => $name,
            'id'            => $id,
            'description'   => __('Add widgets here to appear in your footer.', 'vh'),
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget'  => '</section>',
            'before_title'  => '<h2 class="widget-title">',
            'after_title'   => '</h2>',
        ]);
    }
}
add_action('widgets_init', 'vh_widgets_init');

/**
 * Custom excerpt length
 */
function vh_excerpt_length($length) {
    return 30;
}
add_filter('excerpt_length', 'vh_excerpt_length', 999);

/**
 * Custom excerpt more
 */
function vh_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'vh_excerpt_more');

/**
 * Allow SVG uploads (admins only)
 */
function vh_allow_svg_uploads($mimes) {
    if (current_user_can('manage_options')) {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
    }
    return $mimes;
}
add_filter('upload_mimes', 'vh_allow_svg_uploads');

/**
 * Fix SVG MIME type detection
 */
function vh_fix_svg_mime_type($data, $file, $filename, $mimes) {
    if (pathinfo($filename, PATHINFO_EXTENSION) === 'svg') {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'vh_fix_svg_mime_type', 10, 4);

/**
 * Disable WPML language selector CSS
 */
define('ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS', true);