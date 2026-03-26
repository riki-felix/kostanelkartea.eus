<?php
/**
 * Single Talk template
 *
 * @package Kostan
 */

get_header();

while ( have_posts() ) : the_post();

$talk_date    = get_field('talk_date');
$talk_lang    = get_field('talk_lang');
$speakers     = get_field('talk_speakers');
$venues       = get_the_terms( get_the_ID(), 'venue' );
$areas        = get_the_terms( get_the_ID(), 'area' );

// Parse date
$dt_obj = null;
$ts_val = 0;
if ( $talk_date ) {
	$dt_obj = DateTime::createFromFormat('d/m/Y g:i a', $talk_date);
	$ts_val = $dt_obj ? $dt_obj->getTimestamp() : 0;
}

// Get first child area for GAIA
$child_area = null;
if ( ! empty( $areas ) && ! is_wp_error( $areas ) ) {
	foreach ( $areas as $a ) {
		if ( $a->parent !== 0 ) {
			$child_area = $a;
			break;
		}
	}
}

// Get first venue
$venue_name = '';
if ( ! empty( $venues ) && ! is_wp_error( $venues ) ) {
	$venue_name = $venues[0]->name;
}

// Speaker names
$speaker_names = [];
if ( ! empty( $speakers ) ) {
	foreach ( $speakers as $speaker ) {
		$sid = is_object( $speaker ) ? $speaker->ID : $speaker;
		$speaker_names[] = get_the_title( $sid );
	}
}
?>

<main id="primary" class="site-main single-talk">

	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

		<header class="single-talk__header">
			<div class="container">

				<?php if ( ! empty( $speaker_names ) ) : ?>
					<h1 class="single-talk__speaker-name">
						<?php echo esc_html( implode( ' eta ', $speaker_names ) ); ?>
					</h1>
				<?php endif; ?>

				<p class="single-talk__title"><?php the_title(); ?></p>

				<div class="single-talk__details">

					<?php if ( $talk_lang ) : ?>
					<div class="single-talk__detail">
						<span class="single-talk__detail-icon"><?php kostan_the_icon('flag'); ?></span>
						<span class="single-talk__detail-label"><?php esc_html_e( 'Hizkuntza', 'kostan' ); ?></span>
						<span class="single-talk__detail-value"><?php echo esc_html( $talk_lang ); ?></span>
					</div>
					<?php endif; ?>

					<?php if ( $child_area ) : ?>
					<div class="single-talk__detail">
						<span class="single-talk__detail-icon"><?php kostan_the_icon('document'); ?></span>
						<span class="single-talk__detail-label"><?php esc_html_e( 'Gaia', 'kostan' ); ?></span>
						<span class="single-talk__detail-value"><?php echo esc_html( $child_area->name ); ?></span>
					</div>
					<?php endif; ?>

					<?php if ( $dt_obj ) : ?>
					<div class="single-talk__detail">
						<span class="single-talk__detail-icon"><?php kostan_the_icon('clock'); ?></span>
						<span class="single-talk__detail-label"><?php esc_html_e( 'Data', 'kostan' ); ?></span>
						<span class="single-talk__detail-value">
							<time datetime="<?php echo esc_attr( $dt_obj->format('Y-m-d\TH:i') ); ?>">
								<?php echo esc_html( date_i18n( 'Y\k\o F\r\e\n ja\n', $ts_val ) ); ?>
							</time>
						</span>
					</div>
					<?php endif; ?>

					<?php if ( $venue_name ) : ?>
					<div class="single-talk__detail">
						<span class="single-talk__detail-icon"><?php kostan_the_icon('location'); ?></span>
						<span class="single-talk__detail-label"><?php esc_html_e( 'Lekua', 'kostan' ); ?></span>
						<span class="single-talk__detail-value"><?php echo esc_html( $venue_name ); ?></span>
					</div>
					<?php endif; ?>

				</div>

			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="single-talk__image">
				<div class="container">
					<?php the_post_thumbnail('large'); ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="single-talk__content">
			<div class="container">
				<?php the_content(); ?>
			</div>
		</div>

		<?php if ( ! empty( $speakers ) ) : ?>
		<section class="single-talk__speakers">
			<div class="container">
				<h2><?php esc_html_e( 'Hizlariak', 'kostan' ); ?></h2>

				<div class="speakers-grid">
					<?php foreach ( $speakers as $speaker ) :
						$speaker_id = is_object( $speaker ) ? $speaker->ID : $speaker;
					?>
					<article class="speaker-card">
						<?php if ( has_post_thumbnail( $speaker_id ) ) : ?>
							<div class="speaker-card__image">
								<?php echo get_the_post_thumbnail( $speaker_id, 'medium' ); ?>
							</div>
						<?php endif; ?>

						<div class="speaker-card__content">
							<h3 class="speaker-card__name">
								<a href="<?php echo esc_url( get_permalink( $speaker_id ) ); ?>">
									<?php echo esc_html( get_the_title( $speaker_id ) ); ?>
								</a>
							</h3>

							<?php
							$excerpt = get_the_excerpt( $speaker_id );
							if ( $excerpt ) : ?>
								<p class="speaker-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
							<?php endif; ?>

							<div class="speaker-card__bio">
								<?php echo apply_filters( 'the_content', get_post_field( 'post_content', $speaker_id ) ); ?>
							</div>
						</div>
					</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php endif; ?>

	</article>

</main>

<?php
endwhile;
get_footer();
