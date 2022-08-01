# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only
TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)
MOD_NAME=example_wc_agent

DIRS = agent ui
TESTDIR = agent_tests ui_tests

dirloop=for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1);  done

all: VERSIONFILE
	$(call dirloop, )

test:
	$(MAKE) -C $(TESTDIR) test

coverage:
	$(MAKE) -C $(TESTDIR) coveraga

# generate the VERSION file
VERSIONFILE:
	$(call WriteVERSIONFile,"example_wc_agent")

install: all
	$(call dirloop,install)
	$(INSTALL_DATA) VERSION $(DESTDIR)$(MODDIR)/$(MOD_NAME)/VERSION
	$(INSTALL_DATA) $(MOD_NAME).conf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_NAME).conf
	@mkdir -p $(DESTDIR)$(SYSCONFDIR)/mods-enabled
	@if test ! -e $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME); then \
	  ln -s $(MODDIR)/$(MOD_NAME) $(DESTDIR)$(SYSCONFDIR)/mods-enabled ; fi;

uninstall:
	$(call dirloop,uninstall)
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)
	rm -f $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME)

clean:
	$(call dirloop,clean)
	rm -f VERSION

.PHONY: all install uninstall clean test coverage VERSIONFILE
