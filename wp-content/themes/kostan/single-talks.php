<?php
/**
 * Single Talk template
 *
 * @package Kostan
 */

get_header();

while ( have_posts() ) : the_post();

$talk_date = get_field('talk_date');
$talk_lang = get_field('talk_lang');
$speakers  = get_field('talk_speakers');
$venues    = get_the_terms( get_the_ID(), 'venue' );
$areas     = get_the_terms( get_the_ID(), 'area' );
?>

<main id="primary" class="site-main single-talk">

	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

		<header class="single-talk__header">
			<div class="wrapper">
				<?php if ( $talk_date ) :
					$dt_obj = DateTime::createFromFormat('d/m/Y g:i a', $talk_date);
					$ts_val = $dt_obj ? $dt_obj->getTimestamp() : 0;
				?>
					<time class="single-talk__date" datetime="<?php echo $dt_obj ? esc_attr( $dt_obj->format('Y-m-d\TH:i') ) : ''; ?>">
						<?php echo esc_html( date_i18n( 'l, j F Y – H:i', $ts_val ) ); ?>
					</time>
				<?php endif; ?>

				<?php the_title( '<h1 class="single-talk__title">', '</h1>' ); ?>

				<div class="single-talk__meta">
					<?php if ( ! empty( $venues ) && ! is_wp_error( $venues ) ) : ?>
						<?php foreach ( $venues as $v ) : ?>
							<a href="<?php echo esc_url( get_term_link( $v ) ); ?>" class="single-talk__tag">
								<?php echo esc_html( $v->name ); ?>
							</a>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if ( $talk_lang ) : ?>
						<span class="single-talk__tag single-talk__tag--lang"><?php echo esc_html( $talk_lang ); ?></span>
					<?php endif; ?>

					<?php if ( ! empty( $areas ) && ! is_wp_error( $areas ) ) : ?>
						<?php foreach ( $areas as $a ) : ?>
							<a href="<?php echo esc_url( get_term_link( $a ) ); ?>" class="single-talk__tag single-talk__tag--area">
								<?php echo esc_html( $a->name ); ?>
							</a>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="single-talk__image">
				<div class="wrapper">
					<?php the_post_thumbnail('large'); ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="single-talk__content">
			<div class="wrapper">
				<?php the_content(); ?>
			</div>
		</div>

		<?php if ( ! empty( $speakers ) ) : ?>
		<section class="single-talk__speakers">
			<div class="wrapper">
				<h2><?php esc_html_e( 'Hizlariak', 'kostan' ); ?></h2>

				<div class="speakers-grid">
					<?php foreach ( $speakers as $speaker ) :
						$speaker_id = is_object( $speaker ) ? $speaker->ID : $speaker;
					?>
					<article class="speaker-card">
						<?php if ( has_post_thumbnail( $speaker_id ) ) : ?>
							<div class="speaker-card__image">
								<?php echo get_the_post_thumbnail( $speaker_id, 'medium' ); ?>
							</div>
						<?php endif; ?>

						<div class="speaker-card__content">
							<h3 class="speaker-card__name">
								<a href="<?php echo esc_url( get_permalink( $speaker_id ) ); ?>">
									<?php echo esc_html( get_the_title( $speaker_id ) ); ?>
								</a>
							</h3>

							<?php
							$excerpt = get_the_excerpt( $speaker_id );
							if ( $excerpt ) : ?>
								<p class="speaker-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
							<?php endif; ?>

							<div class="speaker-card__bio">
								<?php echo apply_filters( 'the_content', get_post_field( 'post_content', $speaker_id ) ); ?>
							</div>
						</div>
					</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php endif; ?>

	</article>

</main>

<?php
endwhile;
get_footer();
