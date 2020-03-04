# Copyright Siemens AG 2019-2020
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DIRS = Unit Functional
LOCALAGENTDIR = ../agent/

DIR_LOOP = @set -e; for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1); done

all:
	$(call DIR_LOOP, )

test:
	$(call DIR_LOOP,test)

coverage:
	$(call DIR_LOOP,coverage)
	$(call coverage-report-html,$(LOCALAGENTDIR))

clean:
	$(call DIR_LOOP,clean)

.PHONY: all test coverage clean
