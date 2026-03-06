<?php
/**
 * The template for displaying all single posts
 *
 * @package V&H
 */

get_header();
?>

<main id="primary" class="site-main">
    <div class="container">
        <?php
        while (have_posts()) :
            the_post();
            get_template_part('template-parts/content', 'single');
        endwhile;
        ?>
    </div>
</main>

<?php
get_footer();
