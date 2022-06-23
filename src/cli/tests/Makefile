# SPDX-FileCopyrightText: © 2012-2014 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

all:

test: fossology_testconfig phpvendors
	@echo "make functional test for cli scripts"
	$(MAKE) -C $(TOP)
	phpunit --log-junit cli-Xunit.xml --configuration tests.xml

coverage:
	@echo "no coverage for cli/tests"

clean:
	rm -f cli-Xunit.xml

phpvendors:
	$(MAKE) -C $(TOP)/src $@

.PHONY: all test coverage clean

include $(DEPS)
