<?php
/**
 * ACF Block: Deskargak item.
 *
 * @package Kostan
 */

if ( ! function_exists( 'kostan_deskargak_detect_type' ) ) {
	function kostan_deskargak_detect_type( $file ) {
		$filename  = '';
		$mime_type = '';

		if ( is_array( $file ) ) {
			$filename  = isset( $file['filename'] ) ? (string) $file['filename'] : '';
			$mime_type = isset( $file['mime_type'] ) ? (string) $file['mime_type'] : '';
		}

		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		switch ( $extension ) {
			case 'pdf':
				return 'pdf';
			case 'zip':
			case 'rar':
			case '7z':
				return 'zip';
			case 'doc':
			case 'docx':
			case 'odt':
				return 'doc';
			case 'xls':
			case 'xlsx':
			case 'csv':
				return 'xls';
			case 'ppt':
			case 'pptx':
				return 'ppt';
			case 'jpg':
			case 'jpeg':
			case 'png':
			case 'gif':
			case 'webp':
			case 'svg':
				return 'image';
			case 'mp4':
			case 'mov':
			case 'avi':
			case 'webm':
				return 'video';
			case 'mp3':
			case 'wav':
			case 'ogg':
			case 'm4a':
				return 'audio';
		}

		if ( 0 === strpos( $mime_type, 'image/' ) ) {
			return 'image';
		}

		if ( 0 === strpos( $mime_type, 'video/' ) ) {
			return 'video';
		}

		if ( 0 === strpos( $mime_type, 'audio/' ) ) {
			return 'audio';
		}

		return 'other';
	}
}

if ( ! function_exists( 'kostan_deskargak_get_svg' ) ) {
	function kostan_deskargak_get_svg( $filename, $class ) {
		$file = trailingslashit( get_template_directory() ) . 'assets/icons/' . ltrim( (string) $filename, '/' );

		if ( ! file_exists( $file ) ) {
			return '';
		}

		$svg = file_get_contents( $file );
		if ( false === $svg || '' === $svg ) {
			return '';
		}

		$css_class = esc_attr( trim( (string) $class ) );
		if ( $css_class ) {
			if ( false !== strpos( $svg, 'class=' ) ) {
				$svg = preg_replace( '/<svg\s+class="([^"]*)"/i', '<svg class="$1 ' . $css_class . '"', $svg, 1 );
			} else {
				$svg = preg_replace( '/<svg\s+/i', '<svg class="' . $css_class . '" ', $svg, 1 );
			}
		}

		return $svg;
	}
}

if ( ! function_exists( 'kostan_deskargak_download_icon' ) ) {
	function kostan_deskargak_download_icon() {
		$svg = kostan_deskargak_get_svg( 'download-icon.svg', 'deskargak-card__download-icon' );

		if ( '' !== $svg ) {
			return $svg;
		}

		return '';
	}
}

$file = get_field( 'file' );
$type = (string) get_field( 'type' );

if ( empty( $file['url'] ) ) {
	if ( ! empty( $is_preview ) ) {
		echo '<div class="deskargak-card deskargak-card--placeholder"><p><em>' . esc_html__( 'Selecciona un archivo en los ajustes del item.', 'kostan' ) . '</em></p></div>';
	}
	return;
}

$resolved      = 'auto' === $type || '' === $type ? kostan_deskargak_detect_type( $file ) : sanitize_key( $type );
$title         = (string) get_field( 'title' );
$title         = $title ? $title : ( ! empty( $file['title'] ) ? (string) $file['title'] : (string) $file['filename'] );
$filename      = ! empty( $file['filename'] ) ? (string) $file['filename'] : basename( (string) $file['url'] );
$extension     = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
$filename_base = '';
$uploaded_date = '';

if ( '' !== $extension ) {
	$filename_base = preg_replace( '/\.' . preg_quote( $extension, '/' ) . '$/i', '', $filename );
}

if ( '' === $filename_base ) {
	$filename_base = $filename;
}

$attachment_id = isset( $file['ID'] ) ? (int) $file['ID'] : 0;
if ( $attachment_id > 0 && get_post_type( $attachment_id ) === 'attachment' ) {
	$uploaded_timestamp = (int) get_post_timestamp( $attachment_id, 'date' );
	if ( $uploaded_timestamp > 0 ) {
		$lang_code = function_exists( 'kostan_get_current_language_code' )
			? kostan_get_current_language_code()
			: strtolower( substr( determine_locale(), 0, 2 ) );

		$date_format = ( 'eu' === $lang_code ) ? 'Y/m/d' : 'd/m/Y';
		$uploaded_date = wp_date( $date_format, $uploaded_timestamp );
	}
}
?>

<a class="deskargak-card deskargak-card--<?php echo esc_attr( $resolved ); ?>" href="<?php echo esc_url( $file['url'] ); ?>" download aria-label="<?php echo esc_attr( sprintf( __( 'Descargar %s', 'kostan' ), $title ) ); ?>">
	<div class="deskargak-card__content">
		<h3 class="deskargak-card__title"><?php echo esc_html( $title ); ?></h3>
		<p class="deskargak-card__filename">
			<span class="deskargak-card__download-label"><?php esc_html_e( 'Descargar', 'kostan' ); ?></span>
			<span class="deskargak-card__arrow" aria-hidden="true">&#8594;</span>
			<span class="deskargak-card__filename-base"><?php echo esc_html( $filename_base ); ?></span>
			<?php if ( '' !== $extension ) : ?>
				<span class="deskargak-card__ext deskargak-card__ext--<?php echo esc_attr( $resolved ); ?>">.<?php echo esc_html( $extension ); ?></span>
			<?php endif; ?>
		</p>
		<?php if ( '' !== $uploaded_date ) : ?>
			<p class="deskargak-card__updated"><?php echo esc_html( sprintf( __( 'Actualizado el %s', 'kostan' ), $uploaded_date ) ); ?></p>
		<?php endif; ?>
	</div>
	<span class="deskargak-card__download" aria-hidden="true">
		<?php echo kostan_deskargak_download_icon(); ?>
	</span>
</a>