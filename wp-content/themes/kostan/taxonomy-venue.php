<?php
/**
 * Taxonomy archive: venue
 *
 * @package Kostan
 */

get_header();

$term = get_queried_object();
?>

<main id="primary" class="site-main taxonomy-venue">
	<section class="venue-header">
		<div class="wrapper">
			<h1><?php echo esc_html( $term->name ); ?></h1>

			<?php if ( $term->description ) : ?>
				<div class="venue-description">
					<?php echo wpautop( esc_html( $term->description ) ); ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( have_posts() ) : ?>
	<section class="venue-talks">
		<div class="wrapper">
			<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class('talk-card'); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="talk-card__image">
						<?php the_post_thumbnail('medium_large'); ?>
					</div>
				<?php endif; ?>

				<div class="talk-card__content">
					<h2>
						<a href="<?php the_permalink(); ?>">
							<?php the_title(); ?>
						</a>
					</h2>

					<time class="talk-card__date" datetime="<?php echo esc_attr( get_the_date('c') ); ?>">
						<?php echo esc_html( get_the_date() ); ?>
					</time>

					<?php the_excerpt(); ?>
				</div>
			</article>
			<?php endwhile; ?>

			<?php the_posts_navigation(); ?>
		</div>
	</section>
	<?php endif; ?>
</main>

<?php
get_footer();
