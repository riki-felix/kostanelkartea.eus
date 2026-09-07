# Komunikazioa

Plugin base for campaign emails and Interesdunak leads.

## SMTP

The plugin sends email with `wp_mail()` using the server configured in **Komunikazioa → Ajustes**.

### Option A — Google Workspace SMTP relay (recommended, no 2FA on a mailbox)

Use this when the site should send as `@your-domain` without App Passwords.

1. In [admin.google.com](https://admin.google.com): **Apps → Google Workspace → Gmail → Routing → SMTP relay service**.
2. Allowed senders: only addresses in your domains.
3. Authentication: **Only accept mail from the specified IP addresses** (add the Kinsta site outbound IPv4). Do **not** require SMTP auth if you want to avoid 2FA.
4. Require TLS: on.
5. In **Komunikazioa → Ajustes**:

| Campo | Valor |
| --- | --- |
| Servidor SMTP | `smtp-relay.gmail.com` |
| Puerto | `587` |
| Cifrado | `TLS` |
| Usuario SMTP | *(vacío)* |
| Contraseña SMTP | escribe `CLEAR` una vez y guarda (borra la password antigua) |
| Email del remitente | una dirección real del dominio Workspace |

Then send the SMTP test email from the same settings screen.

### Option B — Gmail mailbox SMTP (needs 2FA + App Password)

| Campo | Valor |
| --- | --- |
| Servidor SMTP | `smtp.gmail.com` |
| Puerto | `587` (TLS) o `465` (SSL) |
| Usuario SMTP | dirección completa del buzón |
| Contraseña SMTP | App Password de Google |
| Email del remitente | misma dirección del buzón |

Use the SMTP test email in **Komunikazioa → Ajustes → Servidor SMTP** to verify delivery after saving the settings.

## Enlaces en correos (dominio público)

Los correos de bienvenida e importación enlazan a `/ongi-etorri/` en el sitio público.

| Entorno | Configuración |
| --- | --- |
| **Producción (Kinsta LIVE)** | Dejad vacío **URL pública del sitio** en Ajustes. WordPress usa `https://kostanelkartea.eus` automáticamente. |
| **Local** | Indicad `https://kostanelkartea.eus` en **Komunikazioa → Ajustes → URL pública del sitio** para que los enlaces de prueba no apunten a `.local`. |

Alternativa en `wp-config.php` local: `define( 'KOMUNIKAZIOA_PUBLIC_SITE_URL', 'https://kostanelkartea.eus' );`

Las páginas `/ongi-etorri/`, `/pasahitza-berreskuratu/` y `/bazkideak/` deben existir en WordPress (ya están en producción).
