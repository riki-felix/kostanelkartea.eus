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

$search_ui = function_exists( 'kostan_search_ui_strings' ) ? kostan_search_ui_strings() : [
	'label'       => 'Buscar',
	'button'      => 'Buscar',
	'placeholder' => 'Buscar actividades y noticias',
];

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
		<div class="container page-ekintzak__header-inner">
			<div class="page-ekintzak__header-row">
				<h1><?php the_title(); ?></h1>
				<form role="search" method="get" class="content-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="search-noticias-input"><?php echo esc_html( $search_ui['label'] ); ?></label>
					<input id="search-noticias-input" type="search" class="input content-search-form__input" placeholder="<?php echo esc_attr( $search_ui['placeholder'] ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
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

	<?php if ( $noticias->have_posts() ) : ?>
	<div class="container">
		<div class="ekintzak-grid">
			<?php while ( $noticias->have_posts() ) : $noticias->the_post(); ?>
			<?php $post_ts = get_post_timestamp( get_the_ID() ); ?>
			<article <?php post_class( 'ekintzak-card' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="ekintzak-card__image">
						<?php the_post_thumbnail( 'medium_large' ); ?>
					</div>
				<?php endif; ?>

				<div class="ekintzak-card__content">
					<time class="ekintzak-card__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
						<?php echo esc_html( kostan_format_timestamp( $post_ts, 'date' ) ); ?>
					</time>

					<h2 class="ekintzak-card__title">
						<?php the_title(); ?>
					</h2>
					<div class="ekintzak-card__excerpt">
						<?php the_content(); ?>
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
