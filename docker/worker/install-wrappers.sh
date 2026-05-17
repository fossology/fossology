#!/bin/sh
# SPDX-FileCopyrightText: © 2024 FOSSology contributors
# SPDX-License-Identifier: GPL-2.0-only
#
# Installs agent-wrapper.sh as a shim in front of every FOSSology agent binary.
#
# For each agent directory under /usr/local/share/fossology/*/agent/,
# the real binary is renamed to <name>.real and the wrapper is copied in
# as <name>. This allows the wrapper to stagger --scheduler_start tests.

WRAPPER=/opt/fossology/agent-wrapper.sh
AGENTS_BASE=/usr/local/share/fossology

echo "[install-wrappers] Scanning for agent binaries under $AGENTS_BASE..."

wrapped=0
for agent_dir in "$AGENTS_BASE"/*/agent; do
  [ -d "$agent_dir" ] || continue

  for bin in "$agent_dir"/*; do
    [ -f "$bin" ] || continue
    [ -x "$bin" ] || continue
    # Skip if already wrapped (has .real counterpart)
    case "$bin" in *.real) continue ;; esac

    bin_name=$(basename "$bin")

    # Rename original binary
    mv "$bin" "${bin}.real"
    # Install wrapper in its place
    cp "$WRAPPER" "$bin"
    chmod +x "$bin"

    wrapped=$((wrapped + 1))
    echo "[install-wrappers]   wrapped: $bin_name"
  done
done

echo "[install-wrappers] Done. Wrapped $wrapped agent binaries."
