<?php
/**
 * Search results template.
 *
 * Shows only Actividades (posts) and Noticias (news CPT).
 *
 * @package Kostan
 */

get_header();

$search_ui = function_exists( 'kostan_search_ui_strings' ) ? kostan_search_ui_strings() : [
	'label'           => 'Buscar',
	'button'          => 'Buscar',
	'placeholder'     => 'Buscar actividades y noticias',
	'results_title'   => 'Resultados de busqueda',
	'results_summary' => '%1$d resultados para "%2$s"',
	'no_results'      => 'No se han encontrado resultados.',
	'type_news'       => 'Noticia',
	'type_activity'   => 'Actividad',
	'no_category'     => 'Sin categoria',
];

$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;

$search_args = [
	's'              => get_search_query(),
	'post_type'      => [ 'post', 'news' ],
	'post_status'    => 'publish',
	'posts_per_page' => 12,
	'paged'          => $paged,
	'orderby'        => 'date',
	'order'          => 'DESC',
];

$search_results = new WP_Query( $search_args );

$get_result_category = static function ( $post_id ) {
	$terms = get_the_terms( $post_id, 'category' );
	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
		return $terms[0]->name;
	}

	$taxonomies = get_object_taxonomies( get_post_type( $post_id ), 'objects' );
	if ( empty( $taxonomies ) ) {
		return '';
	}

	foreach ( $taxonomies as $taxonomy ) {
		if ( ! $taxonomy->public || ! $taxonomy->show_ui || 'post_format' === $taxonomy->name ) {
			continue;
		}

		$terms = get_the_terms( $post_id, $taxonomy->name );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			return $terms[0]->name;
		}
	}

	return '';
};
?>

<main id="primary" class="site-main page-ekintzak page-search-results">
	<header class="page-ekintzak__header">
		<div class="container page-ekintzak__header-inner">
			<div class="page-ekintzak__header-row">
				<h1><?php echo esc_html( $search_ui['results_title'] ); ?></h1>
				<form role="search" method="get" class="content-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="search-results-input"><?php echo esc_html( $search_ui['label'] ); ?></label>
					<input id="search-results-input" type="search" class="input content-search-form__input" placeholder="<?php echo esc_attr( $search_ui['placeholder'] ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
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

	<div class="container">
		<?php if ( $search_results->have_posts() ) : ?>
			<p class="search-results__summary">
				<?php
				echo esc_html(
					sprintf(
						$search_ui['results_summary'],
						(int) $search_results->found_posts,
						get_search_query()
					)
				);
				?>
			</p>

			<div class="ekintzak-grid search-results-grid">
				<?php while ( $search_results->have_posts() ) : $search_results->the_post(); ?>
					<?php
					$post_ts = get_post_timestamp( get_the_ID() );
					$type    = 'news' === get_post_type() ? $search_ui['type_news'] : $search_ui['type_activity'];
					$cat     = $get_result_category( get_the_ID() );
					if ( ! $cat ) {
						$cat = $search_ui['no_category'];
					}
					?>
					<article <?php post_class( 'ekintzak-card search-result-card' ); ?>>
						<div class="ekintzak-card__content">
							<p class="search-result-card__meta">
								<span><?php echo esc_html( $type ); ?></span>
								<span aria-hidden="true">•</span>
								<span><?php echo esc_html( $cat ); ?></span>
							</p>
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

			<?php
			echo paginate_links([
				'total'     => (int) $search_results->max_num_pages,
				'current'   => max( 1, $paged ),
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
			]);
			?>
		<?php else : ?>
			<p class="search-results__summary"><?php echo esc_html( $search_ui['no_results'] ); ?></p>
		<?php endif; ?>
	</div>

	<?php wp_reset_postdata(); ?>
</main>

<?php
get_footer();
