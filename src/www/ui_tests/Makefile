# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

#
# ui_tests makefile
#
TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DIRS =  api
TESTDIR = ui_tests

dirloop=for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1);  done

all:
	$(call dirloop, )

test:
	$(call dirloop,test)

coverage:
	echo "No make coverage for ui_tests"

install: all
	echo "No install needed for UI Functional tests"

uninstall:
	echo "Nothinhg to uninstall for UI Functional tests"

clean:
	echo "Place holder for clean, UI Functional tests"

.PHONY: all install uninstall clean test
