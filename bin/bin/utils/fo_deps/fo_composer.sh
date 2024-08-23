#!/usr/bin/env bash

# Skip composer installation, if offline mode is enabled
if [ "$OFFLINE" = true ]; then
  echo "Offline mode enabled. Skipping composer installation."
  exit 0
fi

if ! command -v composer &> /dev/null; then
  echo "*** Installing Composer ***"
  
  if [[ "$OSTYPE" == "darwin"* ]]; then
    brew install composer
  else
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
  fi
fi

echo "*** Running composer install ***"
composer install --no-dev

# Free up Disk Space
composer clear-cache
