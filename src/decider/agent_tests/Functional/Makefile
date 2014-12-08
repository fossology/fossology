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
	$(MAKE) -C $(FOLIBPHPDIR) services.xml
	$(MAKE) -C $(LOCALAGENTDIR) decider

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
