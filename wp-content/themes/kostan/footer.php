
    <footer id="colophon" class="site-footer">
        <div class="wrapper">
            <?php if (is_active_sidebar('footer-1') || is_active_sidebar('footer-2') || is_active_sidebar('footer-3')) : ?>
                <div class="footer-widgets">
                    <div class="footer-widget-area">
                        <?php if (is_active_sidebar('footer-1')) : ?>
                            <div class="footer-widget">
                                <?php dynamic_sidebar('footer-1'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="footer-widget-area">
                        <?php if (is_active_sidebar('footer-2')) : ?>
                            <div class="footer-widget">
                                <?php dynamic_sidebar('footer-2'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="footer-widget-area">
                        <?php if (is_active_sidebar('footer-3')) : ?>
                            <div class="footer-widget">
                                <?php dynamic_sidebar('footer-3'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </footer>
<?php wp_footer(); ?>

</body>
</html>
