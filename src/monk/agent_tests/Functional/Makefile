# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

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
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) cliTest.php

test-sched:
	@echo "make functional tests for scheduler mode"
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) schedulerTest.php

test-bulk:
	@echo "make functional tests for bulk mode"
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) bulkTest.php

test: all test-cli test-sched test-bulk

coverage: all-cov test-cli test-sched test-bulk

clean:
	@echo "nothing to do"

.PHONY: all test coverage clean
