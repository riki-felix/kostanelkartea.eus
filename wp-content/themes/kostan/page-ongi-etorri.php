<?php
/**
 * Onboarding page template for first password setup.
 *
 * @package Kostan
 */

if ( is_user_logged_in() ) {
	wp_safe_redirect( kostan_get_members_area_url() );
	exit;
}

$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
$login          = isset( $_REQUEST['login'] ) ? wp_unslash( $_REQUEST['login'] ) : '';
$key            = isset( $_REQUEST['key'] ) ? wp_unslash( $_REQUEST['key'] ) : '';
$cookie_name    = 'kostan_onboarding_reset';

if ( '' !== $login && '' !== $key ) {
	$cookie_value = wp_json_encode(
		[
			'login' => $login,
			'key'   => $key,
		]
	);

	if ( is_string( $cookie_value ) ) {
		setcookie( $cookie_name, rawurlencode( $cookie_value ), time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	}
} elseif ( isset( $_COOKIE[ $cookie_name ] ) ) {
	$decoded = json_decode( rawurldecode( wp_unslash( $_COOKIE[ $cookie_name ] ) ), true );
	if ( is_array( $decoded ) ) {
		$login = isset( $decoded['login'] ) ? (string) $decoded['login'] : '';
		$key   = isset( $decoded['key'] ) ? (string) $decoded['key'] : '';
	}
}

$errors = new WP_Error();
$user   = null;

if ( '' !== $login && '' !== $key ) {
	$user = check_password_reset_key( $key, $login );
	if ( is_wp_error( $user ) ) {
		$user = null;
		$errors->add( 'invalid_key', __( 'El enlace para crear la contrasena no es valido o ha caducado.', 'kostan' ) );
	}
} elseif ( 'POST' !== $request_method ) {
	$errors->add( 'missing_key', __( 'Necesitas abrir esta pagina desde el enlace del correo de bienvenida.', 'kostan' ) );
}

if ( 'POST' === $request_method ) {
	$nonce_ok = isset( $_POST['kostan_onboarding_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kostan_onboarding_nonce'] ) ), 'kostan_onboarding_reset' );

	if ( ! $nonce_ok ) {
		$errors->add( 'invalid_nonce', __( 'No hemos podido validar la solicitud. Vuelve a intentarlo.', 'kostan' ) );
	}

	$pass_1         = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
	$pass_2         = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';
	$accepted_terms = ! empty( $_POST['accepted_terms'] );
	$login          = isset( $_POST['login'] ) ? wp_unslash( $_POST['login'] ) : $login;
	$key            = isset( $_POST['key'] ) ? wp_unslash( $_POST['key'] ) : $key;

	$user = check_password_reset_key( $key, $login );
	if ( is_wp_error( $user ) ) {
		$errors->add( 'invalid_key', __( 'El enlace para crear la contrasena no es valido o ha caducado.', 'kostan' ) );
	}

	if ( '' === $pass_1 ) {
		$errors->add( 'empty_password', __( 'Introduce una contrasena.', 'kostan' ) );
	}

	if ( $pass_1 !== $pass_2 ) {
		$errors->add( 'password_mismatch', __( 'Las contrasenas no coinciden.', 'kostan' ) );
	}

	if ( ! $accepted_terms ) {
		$errors->add( 'terms_required', __( 'Debes aceptar las condiciones para continuar.', 'kostan' ) );
	}

	if ( ! $errors->has_errors() && $user instanceof WP_User ) {
		reset_password( $user, $pass_1 );
		update_user_meta( $user->ID, 'kostan_terms_accepted', '1' );
		update_user_meta( $user->ID, 'kostan_terms_accepted_at', current_time( 'mysql', true ) );
		setcookie( $cookie_name, '', time() - HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );

		wp_safe_redirect( kostan_get_members_area_url() );
		exit;
	}
}

$terms_url = get_privacy_policy_url();
if ( ! $terms_url ) {
	$terms_url = home_url( '/arauak-eta-estatutuak/' );
}
$terms_url = apply_filters( 'kostan_onboarding_terms_url', $terms_url );

get_header();
?>

<main id="primary" class="site-main section-onboarding">
	<div class="page-width">
		<header class="section-onboarding__header">
			<h1><?php esc_html_e( 'Ongi etorri', 'kostan' ); ?></h1>
             <?php the_content(); ?>
		</header>

		<section class="section-login section-login--onboarding">
			<div class="form-login">
				<?php if ( $errors->has_errors() ) : ?>
					<div class="onboarding-notice onboarding-notice--error" role="alert">
						<?php foreach ( $errors->get_error_messages() as $message ) : ?>
							<p><?php echo esc_html( $message ); ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! $errors->has_errors() || 'POST' === $request_method ) : ?>
				<form method="post" action="<?php echo esc_url( get_permalink() ); ?>" class="onboarding-form">
					<?php wp_nonce_field( 'kostan_onboarding_reset', 'kostan_onboarding_nonce' ); ?>
					<input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>">
					<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>">

					<p class="login-password">
						<label for="pass1"><?php esc_html_e( 'Nueva contrasena', 'kostan' ); ?></label>
						<input id="pass1" name="pass1" type="password" class="input" autocomplete="new-password" required>
					</p>

					<p class="login-password">
						<label for="pass2"><?php esc_html_e( 'Repite la contrasena', 'kostan' ); ?></label>
						<input id="pass2" name="pass2" type="password" class="input" autocomplete="new-password" required>
					</p>

					<p class="onboarding-form__terms">
						<label for="accepted_terms" class="onboarding-form__terms-label">
							<input id="accepted_terms" name="accepted_terms" type="checkbox" value="1" required>
							<span>
								<?php esc_html_e( 'Acepto las condiciones de uso y privacidad.', 'kostan' ); ?>
								<a href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Leer condiciones', 'kostan' ); ?></a>
							</span>
						</label>
					</p>

					<p class="login-submit">
						<button type="submit" class="button"><?php esc_html_e( 'Activar cuenta', 'kostan' ); ?></button>
					</p>
				</form>
				<?php else : ?>
					<p>
						<a class="button" href="<?php echo esc_url( kostan_get_lost_password_page_url( kostan_get_current_language_code() ) ); ?>"><?php esc_html_e( 'Solicitar un nuevo enlace', 'kostan' ); ?></a>
					</p>
				<?php endif; ?>
			</div>
		</section>
	</div>
</main>

<?php
get_footer();
