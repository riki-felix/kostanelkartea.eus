<?php
$class_name = 'deskargak-grid';
if ( ! empty( $block['className'] ) ) {
	$class_name .= ' ' . $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_name .= ' align' . $block['align'];
}

$block_id = 'deskargak-' . ( $block['id'] ?? uniqid() );
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( $class_name ); ?>">
	<div class="deskargak-grid__items">
		<?php if ( ! empty( $is_preview ) ) : ?>
			<InnerBlocks
				allowedBlocks="<?php echo esc_attr( wp_json_encode( [ 'acf/deskargak-item' ] ) ); ?>"
				template="<?php echo esc_attr( wp_json_encode( [ [ 'acf/deskargak-item' ] ] ) ); ?>"
				renderAppender="InnerBlocks.ButtonBlockAppender"
			/>
		<?php else : ?>
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</div>
</section>