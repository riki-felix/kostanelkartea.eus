<?php
/**
 * ACF Block: Recintos (Venue Info)
 *
 * Queries all venue taxonomy terms and renders each one
 * with its ACF term fields: venue_photo (image) and location (Google Map).
 *
 * @package Kostan
 */

$class_name = 'section-venue';
if ( ! empty( $block['className'] ) ) {
	$class_name .= ' ' . $block['className'];
}

$venues = get_terms([
	'taxonomy'   => 'venue',
	'hide_empty' => false,
]);

if ( is_wp_error( $venues ) || empty( $venues ) ) {
	if ( $is_preview ) {
		echo '<p><em>' . esc_html__( 'No hay recintos disponibles.', 'kostan' ) . '</em></p>';
	}
	return;
}
?>

<section class="<?php echo esc_attr( $class_name ); ?>">
    <div class="container">
        <?php foreach ( $venues as $venue ) :
            $term_key = 'venue_' . $venue->term_id;
            $photo    = get_field( 'venue_photo', $term_key );
            $location = get_field( 'location', $term_key );

            // Build Google Maps link
            $maps_link = '';
            $address   = '';
            if ( $location ) {
                $address = $location['address'] ?? '';
                $lat     = $location['lat'] ?? '';
                $lng     = $location['lng'] ?? '';
                if ( $lat && $lng ) {
                    $maps_link = 'https://www.google.com/maps/?q=' . urlencode( $lat . ',' . $lng );
                } elseif ( $address ) {
                    $maps_link = 'https://www.google.com/maps/search/' . urlencode( $address );
                }
            }
        ?>
            <div class="venue-block">
                <a href="<?php echo esc_url( get_term_link( $venue ) ); ?>" class="venue-block__link">
                <?php if ( $photo ) :
                    $img_url = is_array( $photo ) ? $photo['url'] : wp_get_attachment_url( $photo );
                    $img_alt = is_array( $photo ) ? ( $photo['alt'] ?: $venue->name ) : $venue->name;
                ?>
                    <div class="venue-block__photo">
                        <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $img_alt ); ?>" loading="lazy" />
                    </div>
                <?php endif; ?>

                <div class="venue-block__content">
                    <h3 class="venue-block__name"><?php echo esc_html( $venue->name ); ?></h3>

                    <?php if ( $address ) : ?>
                        <p class="venue-block__address">
                            <?php if ( $maps_link ) : ?>
                                <?php echo esc_html( $address ); ?>
                            <?php else : ?>
                                <?php echo esc_html( $address ); ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <span class="venue-block__link-text">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 0C15.5228 0 20 4.47715 20 10C20 15.5228 15.5228 20 10 20C4.47715 20 0 15.5228 0 10C0 4.47715 4.47715 0 10 0ZM10 2C5.58172 2 2 5.58172 2 10C2 14.4183 5.58172 18 10 18C14.4183 18 18 14.4183 18 10C18 5.58172 14.4183 2 10 2ZM10.293 5.29297C10.6835 4.90244 11.3165 4.90244 11.707 5.29297L15.707 9.29297C16.0976 9.68349 16.0976 10.3165 15.707 10.707L11.707 14.707C11.3165 15.0976 10.6835 15.0976 10.293 14.707C9.90244 14.3165 9.90244 13.6835 10.293 13.293L12.5859 11H5C4.44772 11 4 10.5523 4 10C4 9.44771 4.44772 9 5 9H12.5859L10.293 6.70703C9.90244 6.31651 9.90244 5.68349 10.293 5.29297Z" fill="#269BC6"/>
                        </svg>
                        <?php esc_html_e( 'Ver detalles', 'kostan' ); ?>
                    </span>
                </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
