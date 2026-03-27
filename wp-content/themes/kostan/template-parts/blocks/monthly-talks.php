<?php
/**
 * ACF Block: Ponencias del mes (Monthly Talks)
 *
 * Displays talks from the current month in a grid.
 *
 * @package Kostan
 */


$now_ts          = current_time( 'timestamp' );
$today_start_ts  = strtotime( wp_date( 'Y-m-d 00:00:00', $now_ts ) );
$current_month   = wp_date( 'Y-m', $now_ts );
$next_month      = wp_date( 'Y-m', strtotime( '+1 month', $now_ts ) );
$talks_by_month  = [
	$current_month => [],
	$next_month    => [],
];

$all_talks = new WP_Query([
	'post_type'      => 'talks',
	'posts_per_page' => -1,
	'meta_key'       => 'talk_date',
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
]);

if ( $all_talks->have_posts() ) {
	while ( $all_talks->have_posts() ) {
		$all_talks->the_post();

		$talk_date = get_field( 'talk_date', get_the_ID() );
		if ( ! $talk_date ) {
			continue;
		}

		$dt_obj = DateTime::createFromFormat( 'd/m/Y g:i a', $talk_date );
		if ( ! $dt_obj ) {
			$dt_obj = DateTime::createFromFormat( 'd/m/Y H:i', $talk_date );
		}
		if ( ! $dt_obj ) {
			continue;
		}

		$talk_ts        = $dt_obj->getTimestamp();
		$talk_month_key = wp_date( 'Y-m', $talk_ts );
		$talk_day_start = strtotime( wp_date( 'Y-m-d 00:00:00', $talk_ts ) );

		if ( $talk_day_start < $today_start_ts ) {
			continue;
		}

		if ( isset( $talks_by_month[ $talk_month_key ] ) ) {
			$talks_by_month[ $talk_month_key ][] = get_the_ID();
		}
	}
	wp_reset_postdata();
}

$selected_month = ! empty( $talks_by_month[ $current_month ] ) ? $current_month : $next_month;
$selected_ids   = $talks_by_month[ $selected_month ] ?? [];

if ( empty( $selected_ids ) ) {
	if ( ! empty( $is_preview ) ) {
		echo '<p><em>' . esc_html__( 'No hay ponencias proximamente.', 'kostan' ) . '</em></p>';
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
		<h2><?php echo esc_html( wp_date( 'F Y', strtotime( $selected_month . '-01' ) ) ); ?></h2>

		<div class="talks-grid">
			<?php foreach ( $selected_ids as $talk_id ) :
				global $post;
				$post = get_post( $talk_id );
				if ( ! $post ) {
					continue;
				}
				setup_postdata( $post );
				get_template_part( 'template-parts/content', 'talk-card' );
			endforeach; ?>
		</div>
	</div>
</section>
<?php wp_reset_postdata(); ?>
