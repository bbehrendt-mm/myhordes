# MyHordes Setup

## Development Installation

```bash
# Install Composer
wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - -q | php -- --quiet
php composer.phar update

# Create database
bin/console doctrine:database:create
bin/console doctrine:schema:update --force
bin/console doctrine:fixtures:load --append

# Install yarn
yarn install
yarn encore dev
```

## Update

```bash
# Update Composer
php composer.phar update

# Update database
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate -n
bin/console doctrine:fixtures:load --append

# Update yarn
yarn install
yarn encore dev
```