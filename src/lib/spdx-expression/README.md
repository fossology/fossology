# SPDX Expression Parser

<!--
SPDX-FileCopyrightText: 2026 FOSSology contributors
SPDX-License-Identifier: GPL-2.0-only
-->

This directory contains the shared parser contract for SPDX license
expressions. Runtime-specific parser implementations must follow this
contract so scanners and the web layer behave identically.

The first implementation lives in `src/lib/c/spdx_expression_parser.*` and is
used by OJO for native scanner-side verification. A future PHP implementation
should consume the same contract and test corpus from this directory.
