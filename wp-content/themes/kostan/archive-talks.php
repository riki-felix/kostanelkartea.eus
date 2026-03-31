<?php
/**
 * Archive template: Talks (Egutegia / Calendar)
 *
 * Shows all talks grouped by month in a calendar layout,
 * with an area legend at the top.
 *
 * @package Kostan
 */

get_header();

/* ── Top-level areas with colors ── */
$areas = get_terms([
	'taxonomy'   => 'area',
	'hide_empty' => false,
	'parent'     => 0,
]);

/* ── All talks ordered by talk_date ── */
$all_talks = new WP_Query([
	'post_type'      => 'talks',
	'posts_per_page' => -1,
	'meta_key'       => 'talk_date',
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
]);

/* Group talks by Year-Month */
$grouped = [];

if ( $all_talks->have_posts() ) :
	while ( $all_talks->have_posts() ) : $all_talks->the_post();
		$talk_date = get_field('talk_date');
		$dt        = $talk_date ? DateTime::createFromFormat('d/m/Y g:i a', $talk_date) : null;
		$ts        = $dt ? $dt->getTimestamp() : get_the_time('U');
		$key       = date('Y-m', $ts);
		$day       = date('j', $ts);

		/* Get parent area and its color */
		$post_areas  = kostan_get_post_areas( get_the_ID() );
		$area_color  = '';
		$area_name   = '';
		$area_slug   = '';
		if ( ! empty( $post_areas ) ) {
			foreach ( $post_areas as $pa ) {
				$parent = ( $pa->parent ) ? get_term( $pa->parent, 'area' ) : $pa;
				if ( ! $parent || is_wp_error( $parent ) ) continue;
				$color  = kostan_get_area_field( 'area_color', $parent->term_id );
				if ( $color ) {
					$area_color = $color;
					$area_name  = $parent->name;
					$area_slug  = $parent->slug;
					break;
				}
			}
		}

		/* Speaker names */
		$speakers     = get_field( 'talk_speakers', get_the_ID() );
		$speaker_list = [];
		if ( ! empty( $speakers ) ) {
			foreach ( $speakers as $speaker ) {
				$sid = is_object( $speaker ) ? $speaker->ID : $speaker;
				$speaker_list[] = get_the_title( $sid );
			}
		}

		$grouped[ $key ][] = [
			'id'         => get_the_ID(),
			'day'        => $day,
			'title'      => get_the_title(),
			'permalink'  => get_the_permalink(),
			'area_color' => $area_color,
			'area_name'  => $area_name,
			'area_slug'  => $area_slug,
			'speakers'   => $speaker_list,
		];
	endwhile;
	wp_reset_postdata();
endif;

/* Determine season range for the heading */
$months_keys = array_keys( $grouped );
$first_year  = ! empty( $months_keys ) ? substr( reset( $months_keys ), 0, 4 ) : current_time('Y');
$last_year   = ! empty( $months_keys ) ? substr( end( $months_keys ), 0, 4 ) : current_time('Y');
$season      = ( $first_year === $last_year ) ? $first_year : $first_year . '-' . $last_year;
?>

<main id="primary" class="site-main archive-talks">

	<!-- Header -->
	<section class="calendar-header">
		<div class="wrapper">
			<div class="calendar-header__row">
				<h1 class="calendar-header__title">
					<?php esc_html_e( 'Ponentziaren Egutegia', 'kostan' ); ?>
					<span class="calendar-header__season"><?php echo esc_html( $season ); ?></span>
				</h1>

				<?php if ( ! empty( $areas ) && ! is_wp_error( $areas ) ) : ?>
				<div class="calendar-filter" id="calendar-area-filter" aria-expanded="false">
					<button class="calendar-filter__toggle" type="button" aria-expanded="false">
						<span class="calendar-filter__label"><?php esc_html_e( 'Area guztiak', 'kostan' ); ?></span>
						<svg class="calendar-filter__arrow" width="12" height="8" viewBox="0 0 12 8" aria-hidden="true"><path fill="currentColor" d="M1.41.59 6 5.17 10.59.59 12 2 6 8 0 2z"/></svg>
					</button>
					<ul class="calendar-filter__list" role="listbox">
						<li class="calendar-filter__option calendar-filter__option--active" role="option" data-value="">
							<?php esc_html_e( 'Area guztiak', 'kostan' ); ?>
						</li>
						<?php foreach ( $areas as $area ) :
							$color = kostan_get_area_field( 'area_color', $area->term_id );
						?>
						<li class="calendar-filter__option" role="option" data-value="<?php echo esc_attr( $area->slug ); ?>">
							<span class="calendar-filter__dot" <?php if ( $color ) : ?>style="background-color: <?php echo esc_attr( $color ); ?>"<?php endif; ?>></span>
							<?php echo esc_html( $area->name ); ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- Calendar grid -->
	<?php if ( ! empty( $grouped ) ) : ?>
	<section class="calendar-grid-section">
		<div class="wrapper">
			<div class="calendar-grid">
				<?php foreach ( $grouped as $ym => $talks ) :
					$ts_label = strtotime( $ym . '-01' );
				?>
				<div class="calendar-month">
					<h2 class="calendar-month__title"><?php echo esc_html( date_i18n( 'F', $ts_label ) ); ?></h2>

					<ul class="calendar-month__list">
						<?php foreach ( $talks as $talk ) : ?>
						<li class="calendar-entry" data-area="<?php echo esc_attr( $talk['area_slug'] ); ?>" <?php if ( $talk['area_color'] ) : ?>style="--entry-color: <?php echo esc_attr( $talk['area_color'] ); ?>"<?php endif; ?>>
							<a href="<?php echo esc_url( $talk['permalink'] ); ?>" class="calendar-entry__link">
								<span class="calendar-entry__day"><?php echo esc_html( $talk['day'] ); ?></span>
								<div class="calendar-entry__info">
									<span class="calendar-entry__speakers"><?php echo esc_html( implode( ', ', $talk['speakers'] ) ); ?></span>
									<span class="calendar-entry__title"><?php echo esc_html( $talk['title'] ); ?></span>							<?php if ( ! empty( $talk['speakers'] ) ) : ?>
								</div>
							<?php endif; ?>							</a>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

</main>

<?php
get_footer();
