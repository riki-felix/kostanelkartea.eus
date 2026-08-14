<?php
/**
 * Template Name: Ponentziak
 * Template Post Type: page
 *
 * Page template: Ponentziak (Talks listing)
 *
 * Displays talks for the selected course grouped by month.
 *
 * @package Kostan
 */

get_header();

$course_slug    = kostan_get_requested_course_slug();
$current_course = kostan_get_current_course();
$is_current     = ( $course_slug === $current_course['slug'] );
$all_talks      = new WP_Query( kostan_talks_query_args( array(), $course_slug ) );
$grouped        = kostan_group_talks_by_month( $all_talks );
$current_ym     = current_time( 'Y-m' );
$calendar_url   = kostan_get_course_calendar_url( $course_slug );
?>

<main id="primary" class="site-main page-talks">

	<section class="page-talks__header">
		<div class="wrapper">
			<h1><?php the_title(); ?></h1>
		</div>
	</section>

	<nav class="talks-nav">
		<div class="wrapper">
			<ul class="talks-nav__list">
				<?php foreach ( $grouped as $ym => $post_ids_nav ) :
					$ts_nav     = strtotime( $ym . '-01' );
					$is_past    = $ym < $current_ym;
					$is_current_month = $ym === $current_ym;
				?>
					<li class="talks-nav__item<?php echo $is_past ? ' talks-nav__item--past' : ''; ?><?php echo $is_current_month ? ' talks-nav__item--current' : ''; ?>">
						<a href="#<?php echo esc_attr( date( 'n-Y', $ts_nav ) ); ?>">
							<?php echo esc_html( kostan_format_timestamp( $ts_nav, 'month' ) ); ?>
						</a>
					</li>
				<?php endforeach; ?>
				<li class="talks-nav__item talks-nav__item--calendar">
					<a href="<?php echo esc_url( $calendar_url ); ?>">
						<?php esc_html_e( 'Egutegia', 'kostan' ); ?>
						<?php kostan_the_icon( 'calendar', 16 ); ?>
					</a>
				</li>
				<li class="talks-nav__item talks-nav__item--course">
					<?php kostan_the_course_dropdown( $course_slug, 'listing' ); ?>
				</li>
			</ul>
		</div>
	</nav>

	<?php if ( empty( $grouped ) && $is_current ) :
		$previous = kostan_get_archived_courses();
		$previous = ! empty( $previous ) ? $previous[0] : null;
	?>
		<section class="page-talks__empty">
			<div class="container">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s = academic year like 2026-27 */
							__( 'Laster iragarriko ditugu %1$s ikasturteko hitzaldiak; bitartean, aurreko ikasturtea ikus dezakezu. / Anunciaremos las ponencias del curso %1$s proximamente; mientras tanto puedes ver el curso anterior.', 'kostan' ),
							$current_course['short_label']
						)
					);
					?>
				</p>
				<?php if ( $previous ) : ?>
					<p>
						<a href="<?php echo esc_url( kostan_get_course_listing_url( $previous->slug ) ); ?>">
							<?php echo esc_html( $previous->name ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</section>
	<?php elseif ( ! empty( $grouped ) ) : ?>
		<?php foreach ( $grouped as $ym => $post_ids ) :
			$ts_label = strtotime( $ym . '-01' );
		?>
		<section id="<?php echo esc_attr( date( 'n-Y', $ts_label ) ); ?>" class="page-talks__month">
			<div class="container">
				<h2><?php echo esc_html( kostan_format_timestamp( $ts_label, 'month_year' ) ); ?></h2>

				<div class="talks-grid">
					<?php foreach ( $post_ids as $pid ) :
						global $post;
						$post = get_post( $pid );
						setup_postdata( $post );

						get_template_part( 'template-parts/content', 'talk-card' );
					endforeach; ?>
				</div>
			</div>
		</section>
		<?php endforeach; ?>
		<?php wp_reset_postdata(); ?>
	<?php endif; ?>

</main>

<?php
get_footer();
