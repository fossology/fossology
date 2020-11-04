FROM gitpod/workspace-postgres

RUN sudo apt-get update \
    && sudo apt-get install -y \
      lsb-release sudo git build-essential \
    && sudo rm -rf /var/lib/apt/lists/*
