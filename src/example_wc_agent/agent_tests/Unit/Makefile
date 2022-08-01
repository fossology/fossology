# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

MOD_NAME=example_wc_agent
MOD_SUBDIR=agent_tests
MOD_TEST_DIR=Unit

all:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR)/$(MOD_TEST_DIR)"

test: all
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR)/$(MOD_TEST_DIR) test"

coverage: test
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR)/$(MOD_TEST_DIR) coverage"

clean:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR)/$(MOD_TEST_DIR) clean"

.PHONY: all test coverage clean
