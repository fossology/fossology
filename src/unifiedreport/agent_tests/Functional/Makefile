# SPDX-FileCopyrightText: © 2017 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LOCALAGENTDIR = ../../agent

all: version
	$(MAKE) -C $(FOLIBPHPDIR) services.xml
	$(MAKE) -C $(LOCALAGENTDIR) unifiedreport

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
