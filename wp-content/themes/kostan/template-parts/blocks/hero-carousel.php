<?php
/**
 * ACF Block: Hero Carousel
 *
 * Full-viewport image carousel using Swiper.
 * Slides: image + focal point + optional heading/text.
 *
 * @package Kostan
 */

$slides = get_field( 'hero_slides' );

if ( empty( $slides ) ) {
	if ( ! empty( $is_preview ) ) {
		echo '<p><em>' . esc_html__( 'Añade imágenes al carrusel.', 'kostan' ) . '</em></p>';
	}
	return;
}

$class_name = 'hero-carousel';
if ( ! empty( $block['className'] ) ) {
	$class_name .= ' ' . $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_name .= ' align' . $block['align'];
}

$carousel_id = 'hero-carousel-' . ( $block['id'] ?? uniqid() );
?>

<section id="<?php echo esc_attr( $carousel_id ); ?>" class="<?php echo esc_attr( $class_name ); ?>">
	<div class="swiper hero-carousel__swiper">
		<div class="swiper-wrapper">
			<?php foreach ( $slides as $slide ) :
				$image = $slide['slide_image'];
				if ( ! $image ) continue;

				$src    = $image['url'];
				$alt    = $image['alt'] ?: '';
				$srcset = wp_get_attachment_image_srcset( $image['ID'], 'full' );
				$sizes  = '100vw';
			?>
			<div class="swiper-slide">
				<img
					src="<?php echo esc_url( $src ); ?>"
					<?php if ( $srcset ) : ?>srcset="<?php echo esc_attr( $srcset ); ?>"<?php endif; ?>
					sizes="<?php echo esc_attr( $sizes ); ?>"
					alt="<?php echo esc_attr( $alt ); ?>"
					loading="eager"
				/>
			</div>
			<?php endforeach; ?>
		</div>
		<?php if ( count( $slides ) > 1 ) : ?>
		<div class="swiper-pagination hero-carousel__pagination"></div>
		<?php endif; ?>
	</div>
</section>
