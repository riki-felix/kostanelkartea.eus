<?php
/**
 * Template part: Talk card
 *
 * Reusable card for talks grid. Expects the global $post to be set.
 *
 * ACF fields: talk_date, talk_lang
 * Relationship: speakers
 * Taxonomies: venue, area
 *
 * @package Kostan
 */

$post_id   = get_the_ID();
$talk_date = get_field( 'talk_date', $post_id );
$talk_lang = get_field( 'talk_lang', $post_id );
$speakers  = get_field( 'talk_speakers', $post_id );
$venues    = get_the_terms( $post_id, 'venue' );
$areas     = get_the_terms( $post_id, 'area' );
$thumbnail = get_the_post_thumbnail_url( $post_id, 'large' );
?>

<article <?php post_class( 'talk-card' . ( $thumbnail ? ' talk-card--has-thumbnail' : '' ) ); ?>>

	<a href="<?php the_permalink(); ?>" class="talk-card__link">
		<div class="talk-card__content"<?php echo $thumbnail ? ' style="--talk-card-image: url(' . esc_url( $thumbnail ) . ');"' : ''; ?>>

			<?php if ( $talk_date ) :
				$dt_obj = DateTime::createFromFormat('d/m/Y g:i a', $talk_date);
				$ts_val = $dt_obj ? $dt_obj->getTimestamp() : 0;
			?>
				<time class="talk-card__date" datetime="<?php echo $dt_obj ? esc_attr( $dt_obj->format('Y-m-d\TH:i') ) : ''; ?>">
					<?php echo esc_html( date_i18n( get_option('date_format'), $ts_val ) ); ?>
				</time>
			<?php endif; ?>

			<?php if ( ! empty( $speakers ) ) : ?>
				<div class="talk-card__speakers">
					<?php
					$speaker_names = [];
					foreach ( $speakers as $speaker ) :
						$speaker_id = is_object( $speaker ) ? $speaker->ID : $speaker;
						$speaker_names[] = esc_html( get_the_title( $speaker_id ) );
					endforeach;
					echo implode( ' <span>eta</span> ', $speaker_names );
					?>
				</div>
			<?php endif; ?>

			<h3 class="talk-card__title">
				<?php the_title(); ?>
			</h3>

			<div class="talk-card__tags">
			<?php if ( ! empty( $venues ) && ! is_wp_error( $venues ) ) : ?>
				<?php foreach ( $venues as $v ) : ?>
					<a href="<?php echo esc_url( get_term_link( $v ) ); ?>" class="talk-card__tag talk-card__tag--venue">
						<?php echo esc_html( $v->name ); ?>
					</a>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( $talk_lang ) : ?>
				<span class="talk-card__tag talk-card__tag--lang"><?php echo esc_html( $talk_lang ); ?></span>
			<?php endif; ?>
			</div>

		</div>

	</a>

	<?php
	// Show only the first child area with its area_symbol
	$child_area = null;
	if ( ! empty( $areas ) && ! is_wp_error( $areas ) ) {
		foreach ( $areas as $a ) {
			if ( $a->parent !== 0 ) {
				$child_area = $a;
				break;
			}
		}
	}
	?>
	<?php if ( $child_area ) :
		$area_symbol = get_field( 'area_symbol', 'area_' . $child_area->term_id );
	?>
		<footer class="talk-card__footer">
			<div class="talk-card__areas">
				<a href="<?php echo esc_url( get_term_link( $child_area ) ); ?>" class="talk-card__area">
					<?php if ( $area_symbol ) : ?>
						<img class="talk-card__area-icon" src="<?php echo esc_url( $area_symbol ); ?>" alt="<?php echo esc_attr( $child_area->name ); ?>" loading="lazy" />
					<?php endif; ?>
					<?php echo esc_html( $child_area->name ); ?>
				</a>
			</div>
		</footer>
	<?php endif; ?>

</article>
