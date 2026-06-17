<?php
/**
 * Category archive template (Ekintzak filtered by category)
 *
 * @package Kostan
 */

get_header();

$search_ui = function_exists( 'kostan_search_ui_strings' ) ? kostan_search_ui_strings() : [
	'label'       => 'Buscar',
	'button'      => 'Buscar',
	'placeholder' => 'Buscar actividades y noticias',
];
?>

<main id="primary" class="site-main page-ekintzak">
	<header class="page-ekintzak__header">
		<div class="container page-ekintzak__header-inner">
			<div class="page-ekintzak__header-row">
				<h1><?php single_cat_title(); ?></h1>
				<form role="search" method="get" class="content-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="search-ekintzak-input"><?php echo esc_html( $search_ui['label'] ); ?></label>
					<input id="search-ekintzak-input" type="search" class="input content-search-form__input" placeholder="<?php echo esc_attr( $search_ui['placeholder'] ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
					<input type="hidden" name="post_type[]" value="post" />
					<input type="hidden" name="post_type[]" value="news" />
					<button type="submit" class="button content-search-form__button">
						<?php kostan_the_icon( 'search', 16, 'content-search-form__icon' ); ?>
						<span><?php echo esc_html( $search_ui['button'] ); ?></span>
					</button>
				</form>
			</div>
			<?php echo kostan_render_activity_categories_menu(); ?>
		</div>
	</header>

	<?php if ( have_posts() ) : ?>
	<div class="container">
		<div class="ekintzak-grid">
			<?php while ( have_posts() ) : the_post(); ?>
			<?php $post_ts = get_post_timestamp( get_the_ID() ); ?>
			<article <?php post_class( 'ekintzak-card' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<a href="<?php the_permalink(); ?>" class="ekintzak-card__image">
						<?php the_post_thumbnail( 'medium_large' ); ?>
					</a>
				<?php endif; ?>

				<div class="ekintzak-card__content">
					<time class="ekintzak-card__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
						<?php echo esc_html( kostan_format_timestamp( $post_ts, 'date' ) ); ?>
					</time>

					<h2 class="ekintzak-card__title">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h2>
					<div class="ekintzak-card__excerpt">
						<?php the_excerpt(); ?>
					</div>
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
