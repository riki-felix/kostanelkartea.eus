<?php
/**
 * ACF Block: Bideoa / Vídeo de ponencia
 *
 * Renders the talk_video oEmbed field (YouTube).
 * Reads the field from the current post, not from the block itself.
 *
 * @package Kostan
 */

$talk_video = get_field( 'talk_video', get_the_ID() );

if ( ! $talk_video ) {
	if ( ! empty( $is_preview ) ) {
		echo '<p><em>' . esc_html__( 'Bideoa ikusteko, bideoaren URLa gehitu "Video de la ponencia" eremuan.', 'kostan' ) . '</em></p>';
	}
	return;
}
?>

<div class="single-talk__video">
	<div class="single-talk__video-wrap">
		<?php echo $talk_video; ?>
	</div>
</div>
