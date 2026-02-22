#!/bin/sh
set -e

# Re-generate autoloader so volume-mounted source changes are picked up.
# Without this, the classmap baked at build time goes stale when ./src or
# ./examples are mounted over the image layer at runtime.
composer dump-autoload --no-interaction --quiet 2>/dev/null || true
php app.php cache:clean || true

exec "$@"
