# SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = scheduler

DIRS = agent
TESTDIR = agent_tests

DIR_LOOP = @set -e; for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1); done

all: VERSIONFILE
	$(call DIR_LOOP, )

test: all
	$(MAKE) -C $(TESTDIR) test

coverage: all
	$(MAKE) -C $(TESTDIR) coverage

VERSIONFILE:
	$(call WriteVERSIONFile,$(MOD_NAME))

install: all
	$(call DIR_LOOP,install)
	$(INSTALL_DATA) VERSION $(DESTDIR)$(MODDIR)/$(MOD_NAME)/VERSION
	$(INSTALL_PROGRAM) mod_deps $(DESTDIR)$(MODDIR)/$(MOD_NAME)/mod_deps

uninstall:
	$(call DIR_LOOP,uninstall)
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)

clean:
	$(call DIR_LOOP,clean)
	$(MAKE) -s -C $(TESTDIR) clean
	rm -f VERSION

.PHONY: all test coverage VERSIONFILE install uninstall clean
