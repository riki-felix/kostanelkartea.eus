# Komunikazioa

Plugin base for campaign emails and Interesdunak leads.

## SMTP

The plugin sends email with `wp_mail()` and can use an external SMTP server configured in **Komunikazioa → Ajustes**.

Recommended values for Google Workspace:

| Campo | Valor |
| --- | --- |
| Servidor SMTP | `smtp.gmail.com` |
| Puerto | `587` |
| Cifrado | `TLS` |
| Usuario SMTP | dirección completa del buzón |
| Contraseña SMTP | App Password de Google |
| Email del remitente | misma dirección del buzón |
| Nombre del remitente | nombre visible del remitente |

Use the SMTP test email in **Komunikazioa → Ajustes → Servidor SMTP** to verify delivery after saving the settings.

## Enlaces en correos (dominio público)

Los correos de bienvenida e importación enlazan a `/ongi-etorri/` en el sitio público.

| Entorno | Configuración |
| --- | --- |
| **Producción (Kinsta LIVE)** | Dejad vacío **URL pública del sitio** en Ajustes. WordPress usa `https://kostanelkartea.eus` automáticamente. |
| **Local** | Indicad `https://kostanelkartea.eus` en **Komunikazioa → Ajustes → URL pública del sitio** para que los enlaces de prueba no apunten a `.local`. |

Alternativa en `wp-config.php` local: `define( 'KOMUNIKAZIOA_PUBLIC_SITE_URL', 'https://kostanelkartea.eus' );`

Las páginas `/ongi-etorri/`, `/pasahitza-berreskuratu/` y `/bazkideak/` deben existir en WordPress (ya están en producción).
