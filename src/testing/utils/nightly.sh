#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

# cron wrapper to launch the nightly script as fosstester

cd fossology/tests
today=`date +'%b.%d.%Y'`
export  PATH=$PATH:/usr/local/bin
./Nightly.php > nightly-$today
