# Copyright Siemens AG 2017
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

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
