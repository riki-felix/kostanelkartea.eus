#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ -f .env.deploy ]]; then
	set -a
	# shellcheck disable=SC1091
	source .env.deploy
	set +a
fi

: "${KINSTA_SSH_HOST:?Set KINSTA_SSH_HOST}"
: "${KINSTA_SSH_USER:?Set KINSTA_SSH_USER}"
: "${KINSTA_SSH_PORT:?Set KINSTA_SSH_PORT}"
: "${KINSTA_REMOTE_PATH:?Set KINSTA_REMOTE_PATH}"

DRY_RUN="${DRY_RUN:-false}"
BUILD_THEME="${BUILD_THEME:-true}"
INSTALL_COMPOSER="${INSTALL_COMPOSER:-true}"
PURGE_CACHE="${PURGE_CACHE:-true}"

# Plugins installed via Composer (see composer.json).
COMPOSER_PLUGINS=(
	block-visibility
	carousel-block
	chaty
	post-type-switcher
)

RSYNC_FLAGS=(-avz --delete --itemize-changes)
EXCLUDES=(
	--exclude 'node_modules/'
	--exclude '.git/'
	--exclude '.DS_Store'
	--exclude '*.map'
)

if [[ "$DRY_RUN" == "true" ]]; then
	RSYNC_FLAGS+=(-n)
	echo "Dry run: no files will be changed on the server."
fi

setup_ssh() {
	if [[ -n "${KINSTA_SSH_KEY:-}" ]]; then
		chmod 600 "$KINSTA_SSH_KEY"
		export RSYNC_RSH="ssh -i ${KINSTA_SSH_KEY} -p ${KINSTA_SSH_PORT} -o StrictHostKeyChecking=accept-new"
		SSH_BASE=(ssh -i "$KINSTA_SSH_KEY" -p "$KINSTA_SSH_PORT" -o StrictHostKeyChecking=accept-new)
		return
	fi

	if [[ -z "${KINSTA_SSH_PASSWORD:-}" ]]; then
		echo "Set KINSTA_SSH_PASSWORD or KINSTA_SSH_KEY." >&2
		exit 1
	fi

	if ! command -v sshpass >/dev/null 2>&1; then
		echo "Install sshpass to use password authentication." >&2
		exit 1
	fi

	export SSHPASS="$KINSTA_SSH_PASSWORD"
	export RSYNC_RSH="sshpass -e ssh -p ${KINSTA_SSH_PORT} -o StrictHostKeyChecking=accept-new"
	SSH_BASE=(sshpass -e ssh -p "$KINSTA_SSH_PORT" -o StrictHostKeyChecking=accept-new)
}

sync_path() {
	local source_path="$1"
	local remote_path="$2"

	echo ""
	echo "==> Syncing ${source_path} -> ${remote_path}"

	rsync "${RSYNC_FLAGS[@]}" "${EXCLUDES[@]}" \
		"${source_path}/" \
		"${KINSTA_SSH_USER}@${KINSTA_SSH_HOST}:${remote_path}/"
}

setup_ssh

if [[ "$INSTALL_COMPOSER" == "true" && -f composer.json ]]; then
	echo "==> Installing Composer plugins"
	composer install --no-dev --prefer-dist --optimize-autoloader
fi

if [[ "$BUILD_THEME" == "true" ]]; then
	echo "==> Building theme assets"
	npm ci --prefix wp-content/themes/kostan
	npm run build --prefix wp-content/themes/kostan
fi

REMOTE_BASE="${KINSTA_REMOTE_PATH%/}"

sync_path "wp-content/themes/kostan" "${REMOTE_BASE}/wp-content/themes/kostan"
sync_path "wp-content/plugins/komunikazioa" "${REMOTE_BASE}/wp-content/plugins/komunikazioa"

for plugin in "${COMPOSER_PLUGINS[@]}"; do
	if [[ -d "wp-content/plugins/${plugin}" ]]; then
		sync_path "wp-content/plugins/${plugin}" "${REMOTE_BASE}/wp-content/plugins/${plugin}"
	fi
done

if [[ "$DRY_RUN" != "true" && "$PURGE_CACHE" == "true" ]]; then
	echo ""
	echo "==> Purging Kinsta cache"
	"${SSH_BASE[@]}" "${KINSTA_SSH_USER}@${KINSTA_SSH_HOST}" \
		"cd '${REMOTE_BASE}' && (wp kinsta cache purge --all || wp cache flush || true)"
fi

echo ""
echo "Deploy finished."
