# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

AGENTDIR = ../../agent
TESTFILE = cliParamsTest4WgetAgent.php

all:
	$(MAKE) -C $(AGENTDIR) $@

test: all
	phpunit --log-junit wget_agent-Xunit.xml $(TESTFILE)

coverage:
	@echo "nothing to do"

clean:
	rm -f *.xml

.PHONY: all test coverage clean
