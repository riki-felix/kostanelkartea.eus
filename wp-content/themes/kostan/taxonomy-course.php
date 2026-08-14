<?php
/**
 * Taxonomy archive: course (academic year)
 *
 * Listings live on the Ponentziak page. Redirect old taxonomy URLs there.
 *
 * @package Kostan
 */

$term = get_queried_object();
$slug = ( $term instanceof WP_Term ) ? $term->slug : '';
wp_safe_redirect( kostan_get_course_listing_url( $slug ), 301 );
exit;
