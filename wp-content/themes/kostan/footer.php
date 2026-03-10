
    <footer id="colophon" class="site-footer">
        <div class="container footer-widgets">
            <?php if (is_active_sidebar('footer-1') || is_active_sidebar('footer-2') || is_active_sidebar('footer-3')) : ?>
                <?php if (is_active_sidebar('footer-1')) : ?>
                    <?php dynamic_sidebar('footer-1'); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </footer>
<?php wp_footer(); ?>

</body>
</html>
