# Kostan Elkartea

Sitio WordPress de Kostan Elkartea. El repositorio versiona solo el codigo propio; WordPress core, medios y plugins premium se gestionan fuera de git.

## Que incluye el repositorio

| Ruta | Descripcion |
| --- | --- |
| `wp-content/themes/kostan/` | Tema personalizado |
| `wp-content/plugins/komunikazioa/` | Plugin de campanas, leads y SMTP |
| `composer.json` | Plugins gratuitos instalables por Composer |
| `scripts/deploy-to-kinsta.sh` | Despliegue por rsync/SSH |
| `.github/workflows/deploy-kinsta.yml` | CI/CD en push a `main` |

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

## Despliegue a Kinsta

### Automatico (recomendado) — entorno PRE en Kinsta LIVE

El sitio en Kinsta LIVE (sin dominio final) se trata como **preproduccion**.

1. Configurar secrets en GitHub (ver abajo)
2. En Kinsta PRE (una sola vez): plugins premium + `composer install` por SSH
3. Cada `git push` a `main` compila el tema y sincroniza codigo propio + plugins de Composer

### Manual desde local

```bash
cp .env.deploy.example .env.deploy
# Rellenar credenciales SSH de MyKinsta

DRY_RUN=true ./scripts/deploy-to-kinsta.sh   # vista previa
./scripts/deploy-to-kinsta.sh                # despliegue real
```

## Notas de seguridad

- `wp-config.php` y `.env.deploy` nunca deben subirse a git
- Si `wp-config.php` estuvo versionado anteriormente, conviene rotar claves de base de datos, salts y la API key de Google Maps
