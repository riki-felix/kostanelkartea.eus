<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'Kostan_Elkartea' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'sSYSp2SZP45nc1Cu' );

/** Database hostname */
define( 'DB_HOST', 'devkinsta_db' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'cdu9`o(2:b.I>S?FX1*?Hi;Jw/|p-Q6xK~.5+S;v=PZiWAN.5Z_V6_E/PPFzv*z9' );
define( 'SECURE_AUTH_KEY',   '2yBBT?r.;O7, r^-`&n*H1s1XtN-s$)D8VHENRWsK(vq|Ltth3h(#;~Q!8Can|Pd' );
define( 'LOGGED_IN_KEY',     'R]_FjODpb2CQ{N*U45`G`cOB~aZ^3QaI0H(vH]Mz}xyWUo9nn6%H<~Wzg$bH+c|V' );
define( 'NONCE_KEY',         'swf=kT3L,[UYgqC`Te^2S$E_)KD#1s,a>rc$?m^C?-6?ShePud^Qa13+fqcoj,R)' );
define( 'AUTH_SALT',         '^<KM DRYlu/FmCKch7Z#WDp2[rnEDsJ!}+>=HZ@IbBP _NOw~@0?eRXZ2.L[m}[2' );
define( 'SECURE_AUTH_SALT',  'a _] Jn]]m-nf{|feQ);vtfZTG`p6QAm~>mrR1%WD,2khw<+;?)Ot`TezO8snIXK' );
define( 'LOGGED_IN_SALT',    'N8tqN]_Z$zLAmR=g^9^kS!D**!6_j-8u9Ge=rm/0+PzEn0Mf3%dj0l!e1rzt gb;' );
define( 'NONCE_SALT',        's*wGge%WA_kb>]3.P*&+/[PRSN2.iXf?.rC|&!~f#UtRQ*h|Ae#%!:Na@:14G zu' );
define( 'WP_CACHE_KEY_SALT', 'F2r#`v:t?;Pu&@Zy2OIhQ_G(, 5Fs4&T3>ywmASU>{LPm4m`1)}J_m,nm(TdiLS_' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_AUTO_UPDATE_CORE', true );
define('GOOGLE_MAPS_API_KEY', 'AIzaSyBI8yavLL50sA9OaXyW3NBRnWsRnh_xtXo');
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
