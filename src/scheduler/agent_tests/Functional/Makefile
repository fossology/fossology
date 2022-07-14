# SPDX-FileCopyrightText: © 2011, 2012 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

AGENT_DIR = ../../agent
TEST_AGENTS = ../agents/

REGRESSION = ./Functional.py
TEST_SRC = Functional.xml
TEST_DST = scheduler-Xunit.xml
TEST_SUITE_LIMIT = 120

all:
	@echo "nothing to do"

test: fossology_testconfig
	$(MAKE) -C $(TOP)/src
	$(MAKE) -C $(AGENT_DIR)
	$(MAKE) -C $(TEST_AGENTS) test
	$(REGRESSION) --tests=$(TEST_SRC) --results=$(TEST_DST) --longest=$(TEST_SUITE_LIMIT)

coverage:
	@echo "nothing to do"

clean:
	rm -f $(TEST_DST) *.pyc

.PHONY: all test coverage clean $(PHP_FILES) $(FO_LIB)

include $(DEPS)
