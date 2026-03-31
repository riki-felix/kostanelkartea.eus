<?php
/**
 * Taxonomy archive: area
 *
 * Displays an area page with icon + title header,
 * and all talks for this area grouped by month.
 *
 * @package Kostan
 */

get_header();

$term        = get_queried_object();
$area_symbol = kostan_get_area_field( 'area_symbol', $term->term_id );
if ( is_array( $area_symbol ) ) {
	$area_symbol = $area_symbol['url'] ?? '';
}

/* Get parent area for color */
$parent_term = ( $term->parent ) ? get_term( $term->parent, 'area' ) : $term;
if ( ! $parent_term || is_wp_error( $parent_term ) ) {
	$parent_term = $term;
}
$area_color  = kostan_get_area_field( 'area_color', $parent_term->term_id );

/* ── Fetch all talks for this area (including child terms) ── */
$area_talks = new WP_Query([
	'post_type'      => 'talks',
	'posts_per_page' => -1,
	'meta_key'       => 'talk_date',
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
	'tax_query'      => [
		[
			'taxonomy'         => 'area',
			'field'            => 'term_id',
			'terms'            => $term->term_id,
			'include_children' => true,
		],
	],
]);

/* Group by Year-Month */
$grouped = [];
if ( $area_talks->have_posts() ) :
	while ( $area_talks->have_posts() ) : $area_talks->the_post();
		$talk_date = get_field('talk_date');
		$dt        = $talk_date ? DateTime::createFromFormat('d/m/Y g:i a', $talk_date) : null;
		$ts        = $dt ? $dt->getTimestamp() : get_the_time('U');
		$key       = date( 'Y-m', $ts );
		$grouped[ $key ][] = get_the_ID();
	endwhile;
	wp_reset_postdata();
endif;
?>

<main id="primary" class="site-main taxonomy-area">

	<!-- Header -->
	<section class="area-header">
		<div class="container">
			<div class="area-header__inner">
				<?php if ( $area_symbol ) : ?>
					<img class="area-header__icon" src="<?php echo esc_url( $area_symbol ); ?>" alt="" loading="lazy" />
				<?php endif; ?>
				<h1 class="area-header__title"><?php echo esc_html( $term->name ); ?></h1>
			</div>

			<?php if ( $term->description ) : ?>
				<div class="area-header__description">
					<?php echo wp_kses_post( wpautop( $term->description ) ); ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- Talks by month -->
	<?php if ( ! empty( $grouped ) ) : ?>
	<section class="area-talks">
		<div class="container">
			<?php foreach ( $grouped as $ym => $post_ids ) :
				$ts_label = strtotime( $ym . '-01' );
			?>
			<div class="page-talks__month">
				<h2><?php echo esc_html( date_i18n( 'F Y', $ts_label ) ); ?></h2>

				<div class="talks-grid">
					<?php foreach ( $post_ids as $pid ) :
						global $post;
						$post = get_post( $pid );
						setup_postdata( $post );
						get_template_part( 'template-parts/content', 'talk-card' );
					endforeach; ?>
				</div>
			</div>
			<?php endforeach; ?>
			<?php wp_reset_postdata(); ?>
		</div>
	</section>
	<?php endif; ?>

</main>

<?php
get_footer();
