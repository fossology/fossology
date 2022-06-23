# SPDX-FileCopyrightText: © 2019 Siemens AG

# SPDX-License-Identifier: FSFAP

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
