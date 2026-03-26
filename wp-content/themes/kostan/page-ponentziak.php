<?php
/**
 * Page template: Ponentziak (Talks listing)
 *
 * Displays all talks grouped by month (oldest month first).
 * Uses slug-based page template: page-ponentziak.php
 *
 * @package Kostan
 */

get_header();
?>

<main id="primary" class="site-main page-talks">

	<section class="page-talks__header">
		<div class="wrapper">
			<h1><?php the_title(); ?></h1>
		</div>
	</section>

	<?php
	/* ── Fetch all talks ordered by talk_date (ACF) ── */
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
			$key       = date( 'Y-m', $ts );

			$grouped[ $key ][] = get_the_ID();
		endwhile;
		wp_reset_postdata();
	endif;
	?>

	<?php if ( ! empty( $grouped ) ) :
		$current_ym = current_time('Y-m');
	?>

	<!-- Month anchor nav -->
	<nav class="talks-nav">
		<div class="wrapper">
			<ul class="talks-nav__list">
				<?php foreach ( $grouped as $ym => $post_ids_nav ) :
					$ts_nav   = strtotime( $ym . '-01' );
					$is_past  = $ym < $current_ym;
					$is_current = $ym === $current_ym;
				?>
					<li class="talks-nav__item<?php echo $is_past ? ' talks-nav__item--past' : ''; ?><?php echo $is_current ? ' talks-nav__item--current' : ''; ?>">
						<a href="#<?php echo esc_attr( date( 'n-Y', $ts_nav ) ); ?>">
							<?php echo esc_html( date_i18n( 'F', $ts_nav ) ); ?>
						</a>
					</li>
				<?php endforeach; ?>
				<li class="talks-nav__item talks-nav__item--calendar">
					<a href="<?php echo esc_url( get_post_type_archive_link('talks') ); ?>">
						<?php kostan_the_icon( 'calendar', 16 ); ?>
						<?php esc_html_e( 'Egutegia', 'kostan' ); ?>
					</a>
				</li>
			</ul>
		</div>
	</nav>
        
		<?php foreach ( $grouped as $ym => $post_ids ) :
			$ts_label = strtotime( $ym . '-01' );
		?>
		<section id="<?php echo esc_attr( date( 'n-Y', $ts_label ) ); ?>" class="page-talks__month">
			<div class="container">
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
		</section>
		<?php endforeach; ?>
		<?php wp_reset_postdata(); ?>
	<?php endif; ?>

</main>

<?php
get_footer();
