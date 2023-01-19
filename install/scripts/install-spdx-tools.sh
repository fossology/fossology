#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only
VERSION="1.1.3"
TAG="v${VERSION}"
DOWNLOAD_LOC=$(dirname $0)/../../src/spdx2/agent_tests/Functional

wget -nc "https://github.com/spdx/tools-java/releases/download/$TAG/tools-java-$VERSION.zip" -P "$DOWNLOAD_LOC"
unzip "$DOWNLOAD_LOC"/tools-java-$VERSION.zip tools-java-$VERSION-jar-with-dependencies.jar -d "$DOWNLOAD_LOC"
rm "$DOWNLOAD_LOC"/tools-java-$VERSION.zip
