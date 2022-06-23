# SPDX-FileCopyrightText: © 2014 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

LOCALAGENTDIR = ../../agent

all: version
	$(MAKE) -C $(LOCALAGENTDIR) copyright
	ln -fs $(LOCALAGENTDIR)/copyright ./copyright

version:
	$(MAKE) -C $(LOCALAGENTDIR)/.. VERSIONFILE

test: all test-cli test-sched

coverage: all-cov test-cli test-sched

all-cov: version
	$(MAKE) -C $(LOCALAGENTDIR) copyright_cov
	ln -fs $(LOCALAGENTDIR)/copyright_cov ./copyright

test-cli: all
	@echo "make functional tests for cli mode"
	./shunit2 cli_test.sh

test-sched: all
	@echo "make functional tests for scheduler mode"
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) schedulerTest.php

clean:
	@echo "nothing to do"
	rm -rf testconf copyright

.PHONY: all test coverage clean

include $(DEPS)
