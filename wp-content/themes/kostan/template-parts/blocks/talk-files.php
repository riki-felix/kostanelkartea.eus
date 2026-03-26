<?php
/**
 * ACF Block: Apunteak / Archivos de ponencia
 *
 * Renders the talk_files repeater field.
 * Reads the field from the current post, not from the block itself.
 *
 * @package Kostan
 */

$talk_files = get_field( 'talk_files', get_the_ID() );

if ( empty( $talk_files ) ) {
	if ( ! empty( $is_preview ) ) {
		echo '<p><em>' . esc_html__( 'Apunteak ikusteko, fitxategiak gehitu "Apunteak" eremuan.', 'kostan' ) . '</em></p>';
	}
	return;
}
?>

<div class="single-talk__files">
	<div class="single-talk__detail">
		<span class="single-talk__detail-icon"><?php kostan_the_icon('attachment'); ?></span>
		<span class="single-talk__detail-label"><?php esc_html_e( 'Apunteak', 'kostan' ); ?></span>
		<span class="single-talk__detail-value">
			<?php foreach ( $talk_files as $row ) :
				$f = $row['file'] ?? null;
				if ( ! $f ) continue;
			?>
				<a href="<?php echo esc_url( $f['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $f['title'] ?: $f['filename'] ); ?></a>
			<?php endforeach; ?>
		</span>
	</div>
</div>
