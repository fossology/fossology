# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DIRS = ui
TESTDIR = ui_tests

DIR_LOOP = @set -e; for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1); done

all:
	$(call DIR_LOOP, )

test: all
	$(MAKE) -C $(TESTDIR) test

coverage: all
	$(MAKE) -C $(TESTDIR) coverage

install: all
	$(call DIR_LOOP,install)

uninstall:
	$(call DIR_LOOP,uninstall)

clean:
	$(call DIR_LOOP,clean)

.PHONY: all test coverage install uninstall clean
