<?php
/**
 * ACF Block: Ponencias del mes (Monthly Talks)
 *
 * Displays talks from the current month in a grid.
 *
 * @package Kostan
 */

$year  = current_time('Y');
$month = current_time('m');

$talks = new WP_Query([
	'post_type'      => 'talks',
	'posts_per_page' => -1,
	'meta_key'       => 'talk_date',
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
	'meta_query'     => [
		[
			'key'     => 'talk_date',
			'value'   => $year . '-' . $month,
			'compare' => 'LIKE',
		],
	],
]);

if ( ! $talks->have_posts() ) {
	if ( ! empty( $is_preview ) ) {
		echo '<p><em>' . esc_html__( 'No hay ponencias este mes.', 'kostan' ) . '</em></p>';
	}
	return;
}

$class_name = 'section-talks';
if ( ! empty( $block['className'] ) ) {
	$class_name .= ' ' . $block['className'];
}
?>

<section class="<?php echo esc_attr( $class_name ); ?>">
	<div class="container">
		<h2><?php echo esc_html( date_i18n( 'F Y' ) ); ?></h2>

		<div class="talks-grid">
			<?php while ( $talks->have_posts() ) : $talks->the_post();
				get_template_part( 'template-parts/content', 'talk-card' );
			endwhile; ?>
		</div>
	</div>
</section>
<?php wp_reset_postdata(); ?>
