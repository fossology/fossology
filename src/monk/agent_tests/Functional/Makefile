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

all:
	$(MAKE) -C $(LOCALAGENTDIR)/..

test: test-cli test-sched

test-cli: all
	@echo "make functional tests for cli mode"
	ln -fs $(LOCALAGENTDIR)/monk ./monk
	./shunit2 cli_test.sh

test-sched: all
	@echo "make functional tests for scheduler mode"
	$(PHPUNIT) -c $(PHPUNIT_CONF) scheduler_test.php

coverage: coverage-cli coverage-sched

coverage-cli: all
	@echo "make functional tests for cli mode with coverage"
	$(MAKE) -C $(LOCALAGENTDIR) monk_cov
	ln -fs $(LOCALAGENTDIR)/monk_cov ./monk
	./shunit2 cli_test.sh

coverage-sched: all
	@echo "make functional tests for scheduler mode with coverage"
	$(PHPUNIT) -c $(PHPUNIT_CONF) scheduler_test.php

clean:
	@echo "nothing to do"

.PHONY: all test coverage clean

include $(DEPS)
