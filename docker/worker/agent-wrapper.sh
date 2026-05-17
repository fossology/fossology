#!/bin/bash
# SPDX-FileCopyrightText: © 2024 FOSSology contributors
# SPDX-License-Identifier: GPL-2.0-only
#
# Agent wrapper shim for Kubernetes worker pods.
#
# Problem: fo_scheduler validates every agent type (~26) on every remote
# host simultaneously at startup. Each agent is SSH'd into the worker and
# run with --scheduler_start. The resulting burst can exhaust system
# resources (CPU, DB connections, file descriptors), causing agents to
# crash before sending their VERSION string. The scheduler then globally
# invalidates any agent type that fails on ANY host, breaking that agent
# for the entire cluster.
#
# Solution: On --scheduler_start (the validation path only), this wrapper
# adds a random delay drawn from /dev/urandom, spreading ~26 agents over
# a 30-second window (~1 agent/sec/host). Normal job execution is not
# affected — only the startup validation path is staggered.
#
# Usage: Installed by install-wrappers.sh. Real binaries renamed to .real
# Requires: bash (for exec -a support; dash/sh lack this feature)

REAL_BIN="${0}.real"
ORIG_ARGV0="$0"

if [ ! -x "$REAL_BIN" ]; then
  echo "FATAL: agent-wrapper cannot find real binary: $REAL_BIN" >&2
  exit 1
fi

# Stagger only the scheduler startup test path
for arg in "$@"; do
  if [ "$arg" = "--scheduler_start" ]; then
    # High-entropy delay: 0.000–29.999 seconds using /dev/urandom.
    # Two independent 16-bit reads avoid RANDOM's limited entropy.
    rand_int=$(od -A n -t u2 -N 2 /dev/urandom | tr -d ' ')
    delay_s=$(( rand_int % 30 ))
    rand_ms=$(od -A n -t u2 -N 2 /dev/urandom | tr -d ' ')
    delay_ms=$(printf '%03d' $(( rand_ms % 1000 )))
    sleep "${delay_s}.${delay_ms}"
    break
  fi
done

# Preserve the original full argv[0] path. This keeps the basename as the
# agent name for VERSION lookup while also retaining dirname($argv[0]) for
# PHP agents that bootstrap through fo_wrapper and need fo_wrapper.php on
# their include path.
exec -a "$ORIG_ARGV0" "$REAL_BIN" "$@"
