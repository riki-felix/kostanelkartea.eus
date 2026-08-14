<?php
/**
 * Taxonomy archive: course (academic year)
 *
 * Lists talks for an archived course, grouped by month.
 *
 * @package Kostan
 */

get_header();

$term = get_queried_object();
if ( ! $term instanceof WP_Term ) {
	get_footer();
	return;
}

$all_talks = new WP_Query( kostan_talks_query_args( array(), $term->slug ) );
$grouped   = kostan_group_talks_by_month( $all_talks );
?>

<main id="primary" class="site-main page-talks page-talks--archive">

	<section class="page-talks__header">
		<div class="wrapper">
			<h1>
				<?php
				printf(
					/* translators: %s = academic year label, e.g. 2025-2026 */
					esc_html__( 'Ponentziak %s', 'kostan' ),
					esc_html( $term->name )
				);
				?>
			</h1>
			<?php kostan_the_courses_nav( $term->slug ); ?>
		</div>
	</section>

	<?php if ( ! empty( $grouped ) ) :
		$current_ym = current_time( 'Y-m' );
	?>

	<nav class="talks-nav">
		<div class="wrapper">
			<ul class="talks-nav__list">
				<?php foreach ( $grouped as $ym => $post_ids_nav ) :
					$ts_nav     = strtotime( $ym . '-01' );
					$is_past    = $ym < $current_ym;
					$is_current = $ym === $current_ym;
				?>
					<li class="talks-nav__item<?php echo $is_past ? ' talks-nav__item--past' : ''; ?><?php echo $is_current ? ' talks-nav__item--current' : ''; ?>">
						<a href="#<?php echo esc_attr( date( 'n-Y', $ts_nav ) ); ?>">
							<?php echo esc_html( kostan_format_timestamp( $ts_nav, 'month' ) ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</nav>

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
