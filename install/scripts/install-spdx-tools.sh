#!/bin/bash
# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: GPL-2.0-only
wget -nc https://github.com/spdx/tools/releases/download/v2.2.2/spdx-tools-2.2.2-jar-with-dependencies.jar -P $(dirname $0)/../../src/spdx2/agent_tests/Functional/
