#!/usr/bin/env bash
# PROTOTYPE (throwaway) for "What should the terms page and footer links look like in the Sylius 2 shop?"
# (https://github.com/Setono/SyliusTermsPlugin/issues/96). Never merged.
#
# Spins up the Setono/SyliusPluginSkeleton 2.2.x test app (Sylius 2.2) with MariaDB in Docker, copies the
# prototype (app/) into it and serves it on http://127.0.0.1:8096/en_US/terms/terms-and-conditions
#
# Usage: prototype/terms-page/setup.sh [target-dir]   (needs PHP 8.2+, Composer, Docker, Node + yarn, Symfony CLI)
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
DIR="${1:-$HERE/.shop}"

git clone --branch 2.2.x --depth 1 https://github.com/Setono/SyliusPluginSkeleton.git "$DIR"
cd "$DIR"
composer install -n
cp -R "$HERE/app/." tests/Application/

docker run -d --name terms-prototype-mariadb -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 -p 33066:3306 mariadb:11.6
until docker exec terms-prototype-mariadb mariadb -uroot -e 'select 1' >/dev/null 2>&1; do sleep 1; done

cd tests/Application
echo 'DATABASE_URL=mysql://root@127.0.0.1:33066/terms_prototype_%kernel.environment%?serverVersion=11.6.2-MariaDB' > .env.local
php bin/console doctrine:database:create -n
php bin/console doctrine:schema:create -n
php -d memory_limit=-1 bin/console sylius:fixtures:load -n default
yarn install && yarn build
php bin/console assets:install -n
symfony server:start --no-tls --port=8096 -d --dir=.

echo "Open http://127.0.0.1:8096/en_US/terms/terms-and-conditions  (←/→ page variant, Shift+←/→ footer variant)"
echo "Tear down: (cd $DIR/tests/Application && symfony server:stop); docker rm -f terms-prototype-mariadb"
