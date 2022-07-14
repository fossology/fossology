#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

# cron wrapper to launch the runTestCleanup as fosstester

cd fossology/tests
export  PATH=$PATH:/usr/local/bin
./runTestCleanup.php > cleanup.log
