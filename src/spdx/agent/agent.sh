#!/usr/bin/env bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

`dirname $0`/../../spdx/agent/spdx --outputFormat=`basename "${0%.sh}"` $@
