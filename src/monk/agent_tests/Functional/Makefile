# SPDX-FileCopyrightText: © 2014-2015 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LOCALAGENTDIR = ../../agent

all: version
	$(MAKE) -C $(LOCALAGENTDIR) all
	ln -fs $(LOCALAGENTDIR)/monk ./monk
	ln -fs $(LOCALAGENTDIR)/monkbulk ./monkbulk

all-cov: version
	$(MAKE) -C $(LOCALAGENTDIR) all_cov
	ln -fs $(LOCALAGENTDIR)/monk_cov ./monk
	ln -fs $(LOCALAGENTDIR)/monkbulk_cov ./monkbulk

version:
	$(MAKE) -C $(LOCALAGENTDIR)/.. VERSIONFILE

test-cli:
	@echo "make functional tests for cli mode"
	$(PHPUNIT) --log-junit monk-Xunit1.xml --bootstrap $(PHPUNIT_BOOT) cliTest.php

test-sched:
	@echo "make functional tests for scheduler mode"
	$(PHPUNIT) --log-junit monk-Xunit2.xml --bootstrap $(PHPUNIT_BOOT) schedulerTest.php

test-bulk:
	@echo "make functional tests for bulk mode"
	$(PHPUNIT) --log-junit monk-Xunit3.xml --bootstrap $(PHPUNIT_BOOT) bulkTest.php

test: all test-cli test-sched test-bulk

coverage: all-cov test-cli test-sched test-bulk

clean:
	@echo "nothing to do"

.PHONY: all test coverage clean
