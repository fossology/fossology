# Copyright Siemens AG 2019
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

AGENT_NAME = ojo

LOCALAGENTDIR = ../../agent

all: version agent
	ln -fs $(LOCALAGENTDIR)/$(AGENT_NAME) ./$(AGENT_NAME)

version:
	$(MAKE) -C $(LOCALAGENTDIR)/.. VERSIONFILE

agent:
	$(MAKE) -C $(LOCALAGENTDIR) $(AGENT_NAME)

test: all test-cli test-sched

coverage: all-cov test-cli test-sched

all-cov: version
	$(MAKE) -C $(LOCALAGENTDIR) $(AGENT_NAME)_cov
	ln -fs $(LOCALAGENTDIR)/$(AGENT_NAME)_cov ./$(AGENT_NAME)

test-cli: all
	@echo "make functional tests for cli mode"
	./shunit2 cli_test.sh

test-sched: all
	@echo "make functional tests for scheduler mode"
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) schedulerTest.php

clean:
	@echo "nothing to do"
	rm -rf testconf $(AGENT_NAME)

.PHONY: all test coverage clean

include $(DEPS)
