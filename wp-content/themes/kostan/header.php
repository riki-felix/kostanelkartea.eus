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
	<meta name="theme-color" content="#012615">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
	<link rel="preconnect" href="https://cdn.fonts.net">
	<script type="text/javascript" src="https://cdn.fonts.net/kit/6d5c73ad-97ed-4f2e-8a71-bb70a8d877d5/6d5c73ad-97ed-4f2e-8a71-bb70a8d877d5_enhanced.js" async></script>
	<link rel="stylesheet" type="text/css" href="https://cdn.fonts.net/kit/6d5c73ad-97ed-4f2e-8a71-bb70a8d877d5/6d5c73ad-97ed-4f2e-8a71-bb70a8d877d5_enhanced.css" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
	<header id="masthead" class="site-header">
		<div class="site-branding">
			<a href="<?php echo esc_url(home_url('/')); ?>" rel="home">
	Kostan
			</a>    
		</div>
		<nav id="site-navigation" class="main-navigation">
			<button class="menu-toggle" aria-controls="menu-panel" aria-expanded="false">
				<span class="menu-toggle-icon">
					MENU
				</span>
			</button>
			<?php do_action('wpml_add_language_selector'); ?>	
			<div class="actions">
				<a class="button-whatsapp" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
					+34 690º5
				</a>
				<a href="/reservas" class="button-cta"><?php _e('Reservar','vh'); ?></a>
			</div>
		</nav>
		<div class="main-cta">
			<div class="contact-cta">
					<a class="button-whatsapp" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
						+34 690 
					</a>
			</div>
			<a href="/reservas" class="button-cta"><?php _e('Reservar','vh'); ?></a>
		</div>
		<div id="menu-panel" class="menu-panel">
			<button class="menu-toggle" aria-controls="menu-panel" aria-expanded="true"><?php _e('Cerrar','vh');?></button>
			<span class="nav-kicker"><?php _e('Propiedades','vh');?> </span>
			<?php
			wp_nav_menu(array(
				'menu_id'        => 'Ciudades',
				'container'      => 'ul',
				'menu_class'     => 'cities-menu',
			));
			?>
			<?php
			wp_nav_menu(array(
				'theme_location' => 'primary',
				'menu_id'        => 'principal',
				'container'      => 'ul',
				'menu_class'     => 'primary-menu',
			));
			?>					
		</div>
	</header>