<?php
/**
 * Blog posts page template (Ekitzak)
 *
 * Used when a static page is set as the "Posts page" in Settings > Reading.
 *
 * @package Kostan
 */

get_header();
?>

<main id="primary" class="site-main page-ekitzak">
	<header class="page-ekitzak__header">
		<div class="wrapper">
			<h1><?php single_post_title(); ?></h1>
		</div>
	</header>

	<?php if ( have_posts() ) : ?>
	<div class="wrapper">
		<div class="ekitzak-grid">
			<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class( 'ekitzak-card' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<a href="<?php the_permalink(); ?>" class="ekitzak-card__image">
						<?php the_post_thumbnail( 'medium_large' ); ?>
					</a>
				<?php endif; ?>

				<div class="ekitzak-card__content">
					<time class="ekitzak-card__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
						<?php echo esc_html( get_the_date() ); ?>
					</time>

					<h2 class="ekitzak-card__title">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h2>
				</div>
			</article>
			<?php endwhile; ?>
		</div>

		<?php the_posts_pagination([
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
		]); ?>
	</div>
	<?php endif; ?>
</main>

<?php
get_footer();
