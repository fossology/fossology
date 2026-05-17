#!/bin/sh
# SPDX-FileCopyrightText: © 2024 FOSSology contributors
# SPDX-License-Identifier: GPL-2.0-only
#
# Entrypoint for the FOSSology worker pod.
# Sets up SSH authorized_keys and starts sshd in the foreground.

set -eu

echo "[worker] Starting FOSSology worker entrypoint..."

# Ensure fossy user exists (should already exist in base image)
if ! id -u fossy >/dev/null 2>&1; then
  groupadd -g 999 fossy 2>/dev/null || true
  useradd -m -u 999 -g 999 -s /bin/sh fossy 2>/dev/null || true
  echo "[worker] Created missing fossy user."
fi

# The web pod writes repository directories as www-data:www-data with 0770.
# Add fossy to that group so remote agent sessions can traverse and unpack
# uploads on the shared volume.
usermod -a -G www-data fossy 2>/dev/null || true

# Install SSH authorized_keys from mounted secret
if [ -f /run/secrets/ssh/authorized_keys ]; then
  cp /run/secrets/ssh/authorized_keys /root/.ssh/authorized_keys
  chmod 600 /root/.ssh/authorized_keys

  mkdir -p /home/fossy/.ssh
  cp /run/secrets/ssh/authorized_keys /home/fossy/.ssh/authorized_keys
  chmod 700 /home/fossy/.ssh
  chmod 600 /home/fossy/.ssh/authorized_keys
  chown -R fossy:fossy /home/fossy/.ssh

  echo "[worker] SSH authorized_keys installed for root and fossy."
else
  echo "[worker] WARNING: /run/secrets/ssh/authorized_keys not found."
  echo "[worker] SSH connections will fail. Mount the SSH secret."
fi

# Ensure Db.conf is present (mounted from configmap or secret)
if [ -f /usr/local/etc/fossology/Db.conf ]; then
  echo "[worker] Db.conf is present."
else
  echo "[worker] WARNING: /usr/local/etc/fossology/Db.conf not found."
fi

# Regenerate host keys if missing (e.g. fresh emptyDir volume)
ssh-keygen -A 2>/dev/null || true

echo "[worker] Starting sshd in foreground..."
exec /usr/sbin/sshd -D -e
