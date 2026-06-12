# Komunikazioa

Plugin base for campaign emails and Interesdunak leads.

## SMTP

The plugin sends email with `wp_mail()` and can use an external SMTP server when the `KOMUNIKAZIOA_SMTP_*` constants are defined in `wp-config.php`.

Recommended hidden configuration for Google Workspace:

```php
define( 'KOMUNIKAZIOA_SMTP_HOST', 'smtp.gmail.com' );
define( 'KOMUNIKAZIOA_SMTP_PORT', 587 );
define( 'KOMUNIKAZIOA_SMTP_ENCRYPTION', 'tls' );
define( 'KOMUNIKAZIOA_SMTP_USER', 'komunikazioa@example.org' );
define( 'KOMUNIKAZIOA_SMTP_PASSWORD', 'app-password' );
define( 'KOMUNIKAZIOA_SMTP_FROM_EMAIL', 'komunikazioa@example.org' );
define( 'KOMUNIKAZIOA_SMTP_FROM_NAME', 'Kostan Elkartea' );
```

The admin only exposes non-sensitive sender settings. SMTP credentials remain outside the database.
