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

if ( ! is_user_logged_in() ) {
	return;
}

$valid_files = [];
if ( is_array( $talk_files ) ) {
	foreach ( $talk_files as $row ) {
		$f = $row['file'] ?? null;

		if ( is_array( $f ) && ! empty( $f['url'] ) ) {
			$valid_files[] = [
				'url'   => $f['url'],
				'title' => $f['title'] ?? ( $f['filename'] ?? '' ),
			];
			continue;
		}

		if ( is_numeric( $f ) ) {
			$file_id = (int) $f;
			$file_url = wp_get_attachment_url( $file_id );
			if ( $file_url ) {
				$valid_files[] = [
					'url'   => $file_url,
					'title' => get_the_title( $file_id ) ?: wp_basename( $file_url ),
				];
			}
			continue;
		}

		if ( is_string( $f ) && ! empty( $f ) ) {
			$valid_files[] = [
				'url'   => $f,
				'title' => wp_basename( $f ),
			];
		}
	}
}

if ( empty( $valid_files ) ) {
	if ( ! empty( $is_preview ) ) {
		echo '<p><em>' . esc_html__( 'Apunteak ikusteko, fitxategiak gehitu "Apunteak" eremuan.', 'kostan' ) . '</em></p>';
	}
	return;
}
?>

<div class="single-talk__detail single-talk__detail--files">
	<span class="single-talk__detail-icon"><?php kostan_the_icon('attachment'); ?></span>
	<span class="single-talk__detail-label"><?php esc_html_e( 'Apunteak', 'kostan' ); ?></span>
	<span class="single-talk__detail-value">
		<?php foreach ( $valid_files as $f ) : ?>
			<a href="<?php echo esc_url( $f['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $f['title'] ); ?></a>
		<?php endforeach; ?>
	</span>
</div>
