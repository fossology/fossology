# SPDX-FileCopyrightText: © 2017 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LOCALAGENTDIR = ../../agent

all:
	@echo "No action to make api tests"

test: all
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) --configuration tests.xml

coverage: all
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) --coverage-html ./results --configuration tests.xml

clean:
	@echo "nothing to do"

.PHONY: all test coverage clean
