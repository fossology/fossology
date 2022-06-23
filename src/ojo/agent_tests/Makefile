# SPDX-FileCopyrightText: © 2019 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LOCALAGENTDIR = ../agent

DIRS = Unit Functional

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
	rm -rf results/

.PHONY: all test coverage clean
