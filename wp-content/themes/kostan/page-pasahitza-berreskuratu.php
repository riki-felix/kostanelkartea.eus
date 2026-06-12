<?php
/**
 * Frontend lost password page.
 *
 * @package Kostan
 */

if ( is_user_logged_in() ) {
	wp_safe_redirect( kostan_get_members_area_url() );
	exit;
}

$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
$user_login     = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
$errors         = new WP_Error();
$mail_sent      = false;

if ( 'POST' === $request_method ) {
	$nonce_ok = isset( $_POST['kostan_lost_password_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kostan_lost_password_nonce'] ) ), 'kostan_lost_password' );

	if ( ! $nonce_ok ) {
		$errors->add( 'invalid_nonce', __( 'No hemos podido validar la solicitud. Vuelve a intentarlo.', 'kostan' ) );
	} elseif ( '' === $user_login ) {
		$errors->add( 'missing_login', __( 'Indica tu correo electronico o nombre de usuario.', 'kostan' ) );
	} else {
		$result = retrieve_password( $user_login );
		if ( is_wp_error( $result ) ) {
			$errors = $result;
		} else {
			$mail_sent = true;
		}
	}
}

get_header();
?>

<main id="primary" class="site-main section-recover-password">
	<div class="page-width">
		<header class="section-recover-password__header">
			<h1><?php esc_html_e( 'Pasahitza berreskuratu', 'kostan' ); ?></h1>
             <?php the_content(); ?>
		</header>

		<section class="section-login section-login--recover">
			<div class="form-login">
				<?php if ( $mail_sent ) : ?>
					<div class="onboarding-notice onboarding-notice--success" role="status">
						<p><?php esc_html_e( 'Mezu elektroniko bat bidali dizugu pasahitza berrezartzeko argibideekin.', 'kostan' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( $errors->has_errors() ) : ?>
					<div class="onboarding-notice onboarding-notice--error" role="alert">
						<?php foreach ( $errors->get_error_messages() as $message ) : ?>
							<p><?php echo esc_html( $message ); ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( get_permalink() ); ?>" class="recover-form">
					<?php wp_nonce_field( 'kostan_lost_password', 'kostan_lost_password_nonce' ); ?>

					<p class="login-username">
						<label for="user_login"><?php esc_html_e( 'Correo o usuario', 'kostan' ); ?></label>
						<input id="user_login" name="user_login" type="text" class="input" value="<?php echo esc_attr( $user_login ); ?>" autocomplete="username" required>
					</p>

					<p class="login-submit">
						<button type="submit" class="button"><?php esc_html_e( 'Bidali berreskuratze esteka', 'kostan' ); ?></button>
					</p>
				</form>
			</div>
		</section>
	</div>
</main>

<?php
get_footer();
