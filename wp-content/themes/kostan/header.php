<?php
$mensaje = apply_filters( 'wpml_translate_single_string',
	'Hola, me interesa una propiedad',
	'WhatsApp Button',
	'whatsapp_mensaje'
);
$url = 'https://wa.me/?text=' . rawurlencode( $mensaje );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#18223A">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,400;0,700;1,400;1,700&family=Barlow+Condensed:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

	<header id="masthead" class="site-header">
		<div class="site-header__inner container">

			<!-- Primary nav (left) -->
			<nav id="site-navigation" class="site-header__nav-primary" aria-label="<?php esc_attr_e('Primary navigation', 'kostan'); ?>">
				<button class="menu-toggle" aria-controls="menu-panel" aria-expanded="false">
					<span class="menu-toggle__bar"></span>
					<span class="menu-toggle__bar"></span>
					<span class="menu-toggle__bar"></span>
				</button>
				<?php
				wp_nav_menu([
					'theme_location' => 'primary',
					'menu_id'        => 'primary-menu',
					'container'      => false,
					'menu_class'     => 'nav-list',
					'fallback_cb'    => false,
					'depth'          => 1,
				]);
				?>
			</nav>

			<!-- Logo (center) -->
			<div class="site-header__logo">
				<a href="<?php echo esc_url( home_url('/') ); ?>" rel="home">
					<?php if ( has_custom_logo() ) :
						the_custom_logo();
					else : ?>
						<span class="site-title"><?php bloginfo('name'); ?></span>
					<?php endif; ?>
				</a>
			</div>

			<!-- Actions nav (right) -->
			<nav class="site-header__nav-actions" aria-label="<?php esc_attr_e('Actions', 'kostan'); ?>">
				<?php
				wp_nav_menu([
					'theme_location' => 'actions',
					'menu_id'        => 'actions-menu',
					'container'      => false,
					'menu_class'     => 'nav-actions',
					'fallback_cb'    => false,
					'depth'          => 1,
				]);
				?>
			</nav>

		</div>
	</header>