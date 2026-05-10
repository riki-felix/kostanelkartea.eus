<?php
/**
 * ACF Block: Últimas noticias (Latest News Slider)
 *
 * Displays the 5 most recent news posts in a Swiper slider
 * with a "Ver todas" button linking to the Noticias page.
 *
 * @package Kostan
 */

// suppress_filters bypasses WPML's SQL language filter so news posts show
// in all site languages regardless of which language they were created in.
$current_lang = apply_filters( 'wpml_current_language', null );

$news = new WP_Query([
	'post_type'        => 'news',
	'posts_per_page'   => 10,
	'orderby'          => 'date',
	'order'            => 'DESC',
	'suppress_filters' => true,
]);

if ( ! $news->have_posts() ) {
	if ( ! empty( $is_preview ) ) {
		echo '<p><em>' . esc_html__( 'No hay noticias publicadas.', 'kostan' ) . '</em></p>';
	}
	wp_reset_postdata();
	return;
}

// Find the Noticias page in the current language.
$noticias_page = get_pages([
	'meta_key'   => '_wp_page_template',
	'meta_value' => 'page-noticias.php',
	'number'     => 1,
]);
$noticias_url = '';
if ( ! empty( $noticias_page ) ) {
	$page_id      = (int) $noticias_page[0]->ID;
	$translated   = (int) apply_filters( 'wpml_object_id', $page_id, 'page', true );
	$noticias_url = get_permalink( $translated ?: $page_id );
}

$class_name = 'section-latest-news';
if ( ! empty( $block['className'] ) ) {
	$class_name .= ' ' . $block['className'];
}

$slider_id = 'latest-news-' . ( $block['id'] ?? uniqid() );
?>

<section id="<?php echo esc_attr( $slider_id ); ?>" class="<?php echo esc_attr( $class_name ); ?>">
	<div class="container">
		<div class="section-latest-news__header">
			<h2><?php esc_html_e( 'Berriak', 'kostan' ); ?></h2>
			<?php if ( $noticias_url ) : ?>
				<a href="<?php echo esc_url( $noticias_url ); ?>" class="section-latest-news__all">
					<?php esc_html_e( 'Ikusi guztiak', 'kostan' ); ?> →
				</a>
			<?php endif; ?>
		</div>

		<div class="swiper latest-news__swiper">
			<div class="swiper-wrapper">
				<?php while ( $news->have_posts() ) : $news->the_post(); ?>
				<?php $post_ts = get_post_timestamp( get_the_ID() ); ?>
				<div class="swiper-slide">
					<article class="news-card <?php echo has_post_thumbnail() ? 'news-card--has-image' : 'news-card--no-image'; ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="news-card__image">
								<?php the_post_thumbnail( 'medium_large' ); ?>
							</div>
						<?php endif; ?>

						<div class="news-card__content">
							<time class="news-card__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
								<?php echo esc_html( kostan_format_timestamp( $post_ts, 'date' ) ); ?>
							</time>
							<h3 class="news-card__title"><?php the_title(); ?></h3>

							<?php if ( has_excerpt() || get_the_content() ) : ?>
								<div class="news-card__excerpt">
									<?php the_excerpt(); ?>
								</div>
							<?php endif; ?>
						</div>
					</article>
				</div>
				<?php endwhile; ?>
			</div>

			<div class="swiper-pagination latest-news__pagination"></div>
		</div>

	</div>
</section>
<?php wp_reset_postdata(); ?>
