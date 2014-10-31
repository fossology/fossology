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

test: test-sched

all: version
	$(MAKE) -C $(LOCALAGENTDIR) ninka
	ln -fs $(LOCALAGENTDIR)/ninka ./ninka

all-cov: version
	$(MAKE) -C $(LOCALAGENTDIR) ninka_cov
	ln -fs $(LOCALAGENTDIR)/ninka_cov ./ninka

version:
	$(MAKE) -C $(LOCALAGENTDIR)/.. VERSIONFILE

test-sched: all
	@echo "make functional tests for scheduler mode"
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) scheduler_test.php

coverage: coverage-sched

coverage-sched: all-cov
	@echo "make functional tests for scheduler mode with coverage"
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) scheduler_test.php

clean:
	@echo "nothing to do"
	rm -rf ninka

.PHONY: all test coverage clean

include $(DEPS)
