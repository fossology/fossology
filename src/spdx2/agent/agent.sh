#!/usr/bin/env bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

`dirname $0`/../../spdx2/agent/spdx2 --outputFormat=`basename "${0%.sh}"` $@
