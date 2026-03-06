<?php
/**
 * Template part for displaying page content
 *
 * @package V&H
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <?php if (is_home() && !is_front_page()) : ?>
        <header class="entry-header">
            <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
        </header>
    <?php endif; ?>

        <?php
        the_content();

        wp_link_pages(array(
            'before' => '<div class="page-links">' . esc_html__('Pages:', 'boulevart'),
            'after'  => '</div>',
        ));
        ?>
</article>
