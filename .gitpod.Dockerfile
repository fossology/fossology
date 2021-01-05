FROM gitpod/workspace-postgres

RUN sudo apt-get update \
 && sudo apt-get install -y \
    lsb-release git build-essential php-xdebug \
 && sudo rm -rf /var/lib/apt/lists/*
