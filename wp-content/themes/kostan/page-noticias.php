<?php
/**
 * Template Name: Noticias
 *
 * Assignable page template for the "noticias" CPT listing.
 * Reuses the ekintzak card layout but without detail links.
 *
 * @package Kostan
 */

get_header();

$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

$noticias = new WP_Query([
	'post_type'      => 'news',
	'posts_per_page' => 12,
	'paged'          => $paged,
	'orderby'        => 'date',
	'order'          => 'DESC',
]);
?>

<main id="primary" class="site-main page-ekintzak">
	<header class="page-ekintzak__header">
		<div class="container">
			<h1><?php the_title(); ?></h1>
		</div>
	</header>

	<?php if ( $noticias->have_posts() ) : ?>
	<div class="container">
		<div class="ekintzak-grid">
			<?php while ( $noticias->have_posts() ) : $noticias->the_post(); ?>
			<article <?php post_class( 'ekintzak-card' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="ekintzak-card__image">
						<?php the_post_thumbnail( 'medium_large' ); ?>
					</div>
				<?php endif; ?>

				<div class="ekintzak-card__content">
					<time class="ekintzak-card__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
						<?php echo esc_html( get_the_date() ); ?>
					</time>

					<h2 class="ekintzak-card__title">
						<?php the_title(); ?>
					</h2>
					<div class="ekintzak-card__excerpt">
						<?php the_excerpt(); ?>
					</div>
				</div>
			</article>
			<?php endwhile; ?>
		</div>

		<?php
		// Pagination
		$total_pages = $noticias->max_num_pages;
		if ( $total_pages > 1 ) :
		?>
		<nav class="navigation pagination" aria-label="<?php esc_attr_e( 'Noticias', 'kostan' ); ?>">
			<div class="nav-links">
				<?php
				echo paginate_links([
					'total'     => $total_pages,
					'current'   => $paged,
					'prev_text' => '&larr;',
					'next_text' => '&rarr;',
				]);
				?>
			</div>
		</nav>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php wp_reset_postdata(); ?>
</main>

<?php
get_footer();
