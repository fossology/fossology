# SPDX-FileCopyrightText: © 2015 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LOCALAGENTDIR = ../../agent

all: version
	$(MAKE) -C $(LOCALAGENTDIR) all

version:
	$(MAKE) -C $(TOP) VERSIONFILE
	$(MAKE) -C $(LOCALAGENTDIR)/.. VERSIONFILE

test-sched:
	@echo "make functional tests for scheduler mode"
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) schedulerTest.php

test: all test-sched

coverage: all test-sched

clean:
	@echo "nothing to do"

.PHONY: all test coverage clean
