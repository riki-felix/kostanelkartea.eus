<?php
/**
 * Front Page template
 *
 * @package Kostan
 */

get_header();
?>

<main id="primary" class="site-main front-page">
	<?php
	while ( have_posts() ) : the_post();
		the_content();
	endwhile;
	?>
</main>

<?php
get_footer();
