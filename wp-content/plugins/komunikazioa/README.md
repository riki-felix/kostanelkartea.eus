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

Use the dashboard test email (`Komunikazioa → Resumen`) to verify delivery after saving the settings.
