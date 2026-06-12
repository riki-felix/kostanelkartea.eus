<?php
/**
 * Category archive template (Ekintzak filtered by category)
 *
 * Displays activities filtered by category, same layout as home.php
 *
 * @package Kostan
 */

get_header();

$search_ui = function_exists( 'kostan_search_ui_strings' ) ? kostan_search_ui_strings() : [
	'label'       => 'Buscar',
	'button'      => 'Buscar',
	'placeholder' => 'Buscar actividades y noticias',
];

$current_term = get_queried_object();
$term_title   = $current_term ? $current_term->name : 'Categoría';
?>

<main id="primary" class="site-main page-ekintzak">
	<header class="page-ekintzak__header">
		<div class="container page-ekintzak__header-inner">
			<div class="page-ekintzak__header-row">
				<h1><?php echo esc_html( $term_title ); ?></h1>
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
					<?php if ( $post_ts ) : ?>
						<time class="ekintzak-card__date" datetime="<?php echo esc_attr( gmdate( 'c', $post_ts ) ); ?>">
							<?php echo esc_html( gmdate( 'j \d\e F \d\e Y', $post_ts ) ); ?>
						</time>
					<?php endif; ?>
					<h3 class="ekintzak-card__title">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h3>
				</div>
			</article>
			<?php endwhile; ?>
		</div>

		<?php the_posts_navigation(); ?>
	</div>
	<?php else : ?>
		<div class="container">
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		</div>
	<?php endif; ?>
</main>

<?php get_footer();
