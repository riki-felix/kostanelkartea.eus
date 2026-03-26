<?php
/**
 * Mono Icons helper
 *
 * Renders inline SVG icons from assets/icons/.
 * Usage: kostan_icon( 'calendar' )        → returns SVG string
 *        kostan_icon( 'calendar', 20 )    → returns SVG with 20×20 size
 *        kostan_the_icon( 'calendar' )    → echoes SVG
 *
 * @package Kostan
 */

/**
 * Get an inline SVG icon by name.
 *
 * @param string   $name  Icon file name without .svg extension.
 * @param int|null $size  Optional size override (width & height).
 * @param string   $class Optional extra CSS class.
 * @return string  SVG markup or empty string if not found.
 */
function kostan_icon( $name, $size = null, $class = '' ) {
	$name = sanitize_file_name( $name );
	$file = get_template_directory() . '/assets/icons/' . $name . '.svg';

	if ( ! file_exists( $file ) ) {
		return '';
	}

	$svg = file_get_contents( $file );

	// Add CSS class
	$css_class = 'icon icon--' . esc_attr( $name );
	if ( $class ) {
		$css_class .= ' ' . esc_attr( $class );
	}
	$svg = str_replace( '<svg ', '<svg class="' . $css_class . '" ', $svg );

	// Override size if provided
	if ( $size ) {
		$size = intval( $size );
		$svg = preg_replace( '/width="[^"]*"/', 'width="' . $size . '"', $svg );
		$svg = preg_replace( '/height="[^"]*"/', 'height="' . $size . '"', $svg );
	}

	// Replace hard-coded fill with currentColor for CSS control
	$svg = str_replace( 'fill="#0D0D0D"', 'fill="currentColor"', $svg );

	return $svg;
}

/**
 * Echo an inline SVG icon.
 */
function kostan_the_icon( $name, $size = null, $class = '' ) {
	echo kostan_icon( $name, $size, $class );
}
