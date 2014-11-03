# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

LOCALAGENTDIR = ../../agent

test: test-cli test-sched

all: version
	$(MAKE) -C $(LOCALAGENTDIR) copyright
	ln -fs $(LOCALAGENTDIR)/copyright ./copyright

all-cov: version
	$(MAKE) -C $(LOCALAGENTDIR) copyright_cov
	ln -fs $(LOCALAGENTDIR)/copyright_cov ./copyright

version:
	$(MAKE) -C $(LOCALAGENTDIR)/.. VERSIONFILE

test-cli: all
	@echo "make functional tests for cli mode"
	./shunit2 cli_test.sh

test-sched: all
	@echo "make functional tests for scheduler mode"
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) scheduler_test.php

coverage: coverage-cli coverage-sched

coverage-cli: all-cov
	@echo "make functional tests for cli mode with coverage"
	$(MAKE) -C $(LOCALAGENTDIR) copyright_cov
	./shunit2 cli_test.sh

coverage-sched: all-cov
	@echo "make functional tests for scheduler mode with coverage"
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) scheduler_test.php

clean:
	@echo "nothing to do"

.PHONY: all test coverage clean

include $(DEPS)
