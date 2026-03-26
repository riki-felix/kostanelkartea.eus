<?php
/**
 * Taxonomy archive: venue
 *
 * Displays a venue detail page with hero image, location info,
 * description, and all talks grouped by month.
 *
 * @package Kostan
 */

get_header();

$term     = get_queried_object();
$term_key = 'venue_' . $term->term_id;
$photo    = get_field( 'venue_photo', $term_key );
$location = get_field( 'location', $term_key );

// Build Google Maps link
$maps_link = '';
$address   = '';
if ( $location ) {
	$address = $location['address'] ?? '';
	$lat     = $location['lat'] ?? '';
	$lng     = $location['lng'] ?? '';
	if ( $lat && $lng ) {
		$maps_link = 'https://www.google.com/maps/?q=' . urlencode( $lat . ',' . $lng );
	} elseif ( $address ) {
		$maps_link = 'https://www.google.com/maps/search/' . urlencode( $address );
	}
}
?>

<main id="primary" class="site-main taxonomy-venue">

	<!-- Hero -->
	<?php if ( $photo ) :
		$img_url = is_array( $photo ) ? $photo['url'] : wp_get_attachment_url( $photo );
		$img_alt = is_array( $photo ) ? ( $photo['alt'] ?: $term->name ) : $term->name;
	?>
	<section class="venue-hero">
		<img class="venue-hero__img" src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $img_alt ); ?>" />
		<div class="venue-hero__overlay">
			<h1 class="venue-hero__title"><?php echo esc_html( $term->name ); ?></h1>
		</div>
	</section>
	<?php else : ?>
	<section class="venue-hero venue-hero--no-image">
		<div class="venue-hero__overlay">
			<h1 class="venue-hero__title"><?php echo esc_html( $term->name ); ?></h1>
		</div>
	</section>
	<?php endif; ?>

	<!-- Location + description -->
	<section class="venue-detail">
		<div class="container">

			<?php if ( $address ) : ?>
			<div class="venue-detail__location">
				<?php kostan_the_icon( 'location', 32 ); ?>
				<p class="venue-detail__address">
					<?php if ( $maps_link ) : ?>
						<a href="<?php echo esc_url( $maps_link ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo nl2br( esc_html( $address ) ); ?>
						</a>
					<?php else : ?>
						<?php echo nl2br( esc_html( $address ) ); ?>
					<?php endif; ?>
				</p>
			</div>
			<?php endif; ?>

			<?php if ( $term->description ) : ?>
			<div class="venue-detail__description">
				<?php echo wp_kses_post( wpautop( $term->description ) ); ?>
			</div>
			<?php endif; ?>

		</div>
	</section>

	<?php
	/* ── Fetch all talks for this venue, ordered by talk_date ── */
	$venue_talks = new WP_Query([
		'post_type'      => 'talks',
		'posts_per_page' => -1,
		'meta_key'       => 'talk_date',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'tax_query'      => [
			[
				'taxonomy' => 'venue',
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			],
		],
	]);

	/* Group by Year-Month */
	$grouped = [];
	if ( $venue_talks->have_posts() ) :
		while ( $venue_talks->have_posts() ) : $venue_talks->the_post();
			$talk_date = get_field('talk_date');
			$dt        = $talk_date ? DateTime::createFromFormat('d/m/Y g:i a', $talk_date) : null;
			$ts        = $dt ? $dt->getTimestamp() : get_the_time('U');
			$key       = date( 'Y-m', $ts );
			$grouped[ $key ][] = get_the_ID();
		endwhile;
		wp_reset_postdata();
	endif;
	?>

	<?php if ( ! empty( $grouped ) ) : ?>
	<section class="venue-ponentziak">
		<div class="container">
			<h2 class="venue-ponentziak__title">
				<?php
				/* translators: %s = venue name */
				printf(
					esc_html__( '%s Ponentziak', 'kostan' ),
					esc_html( $term->name . 'n' )
				);
				?>
			</h2>

			<?php foreach ( $grouped as $ym => $post_ids ) :
				$ts_label = strtotime( $ym . '-01' );
			?>
			<div class="page-talks__month">
				<h2><?php echo esc_html( date_i18n( 'F', $ts_label ) ); ?></h2>

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
