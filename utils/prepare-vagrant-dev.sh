#!/usr/bin/env bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only

sudo apt-get install \
          libcppunit-dev \
          libspreadsheet-writeexcel-perl libdbd-sqlite3-perl \
          default-jre-headless \
          php-sqlite3

pushd $(dirname "${BASH_SOURCE[0]}")

../utils/prepare-test -ft
../install/scripts/install-ninka.sh
../install/scripts/install-spdx-tools.sh

composer install --prefer-dist --working-dir=../src

popd
