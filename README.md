# Kostan Elkartea

Sitio WordPress de Kostan Elkartea. El repositorio versiona solo el codigo propio; WordPress core, medios y plugins premium se gestionan fuera de git.

## Que incluye el repositorio

| Ruta | Descripcion |
| --- | --- |
| `wp-content/themes/kostan/` | Tema personalizado |
| `wp-content/plugins/komunikazioa/` | Plugin de campanas, leads y SMTP |
| `composer.json` | Plugins gratuitos instalables por Composer |
| `scripts/deploy-to-kinsta.sh` | Despliegue por rsync/SSH a Kinsta LIVE |
| `.github/workflows/deploy-kinsta-live.yml` | CI/CD a produccion en push a `main` |

## Requisitos

- [DevKinsta](https://kinsta.com/devkinsta/) o entorno WordPress local
- PHP >= 8.0, Composer, Node.js 20+
- Plugins premium (instalacion manual, ver abajo)

## Configuracion local (DevKinsta)

```bash
# 1. Clonar y entrar en el proyecto dentro de la carpeta public de DevKinsta
cd ~/DevKinsta/public/kostan-elkartea

# 2. Configuracion de WordPress (solo local; no commitear)
cp wp-config.example.php wp-config.php
# Editar wp-config.php con credenciales de DevKinsta

# 3. Plugins gratuitos
composer install

# 4. Plugins premium (copiar desde vuestras licencias)
# Ver seccion "Plugins premium" mas abajo

# 5. Compilar assets del tema
npm ci --prefix wp-content/themes/kostan
npm run build --prefix wp-content/themes/kostan
```

## Plugins premium (instalacion manual)

No estan en git. Instalarlos en cada entorno desde las descargas de vuestras licencias:

| Plugin | Carpeta |
| --- | --- |
| Advanced Custom Fields PRO | `wp-content/plugins/advanced-custom-fields-pro/` |
| WPML Multilingual CMS | `wp-content/plugins/sitepress-multilingual-cms/` |
| WPML String Translation | `wp-content/plugins/wpml-string-translation/` |
| ACFML | `wp-content/plugins/acfml/` |

El instalador OTGS (`otgs-installer-plugin`) suele venir con WPML.

## Despliegue a Kinsta LIVE (produccion)

No hay entorno PRE ni DEV en Kinsta. Local es DevKinsta; produccion es Kinsta LIVE.

El script sincroniza solo codigo (tema, `komunikazioa` y plugins de Composer). No toca la base de datos, uploads ni `wp-config` del servidor.

### Automatico (recomendado)

Cada `git push` a `main` dispara `.github/workflows/deploy-kinsta-live.yml`: compila el tema y sincroniza por rsync/SSH.

Secrets en GitHub (Settings > Secrets and variables > Actions), tomados de MyKinsta > Sites > LIVE > Info > SFTP/SSH:

| Secret | Valor |
| --- | --- |
| `KINSTA_SSH_HOST` | Host SSH |
| `KINSTA_SSH_PORT` | Puerto SSH |
| `KINSTA_SSH_USER` | Usuario SSH |
| `KINSTA_SSH_PASSWORD` | Contrasena SSH |
| `KINSTA_REMOTE_PATH` | Ruta absoluta del `public` (Environment details > Path) |

Una sola vez en LIVE: instalar los plugins premium. Los plugins de Composer los instala CI en el runner y los sube por rsync.

Tambien se puede lanzar a mano desde Actions > Deploy to Kinsta (LIVE production) > Run workflow (dry run por defecto).

### Manual desde local

```bash
cp .env.deploy.example .env.deploy
# Rellenar credenciales SSH de MyKinsta LIVE

DRY_RUN=true ./scripts/deploy-to-kinsta.sh   # vista previa
./scripts/deploy-to-kinsta.sh                # despliegue real a produccion
```

## Notas de seguridad

- `wp-config.php` y `.env.deploy` nunca deben subirse a git
- Si `wp-config.php` estuvo versionado anteriormente, conviene rotar claves de base de datos, salts y la API key de Google Maps
