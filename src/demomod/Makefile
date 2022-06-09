# SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)
MOD_NAME=demomod

DIRS = agent ui
TESTDIR = agent_tests

dirloop=for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1);  done

all: VERSIONFILE
	$(call dirloop, )

test:
	$(call dirloop,test)

# generate the VERSION file
VERSIONFILE:
	$(call WriteVERSIONFile,"demomod")

coverage:
	$(call dirloop,coverage)

install: all
	@echo "make $(MOD_NAME) install"
	$(call dirloop,install)
	$(INSTALL_DATA) VERSION $(DESTDIR)$(MODDIR)/$(MOD_NAME)/VERSION
	$(INSTALL_DATA) $(MOD_NAME).conf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_NAME).conf
	@mkdir -p $(DESTDIR)$(SYSCONFDIR)/mods-enabled
	@if test ! -e $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME); then \
	  ln -s $(MODDIR)/$(MOD_NAME) $(DESTDIR)$(SYSCONFDIR)/mods-enabled ; fi;


uninstall:
	@echo "make $(MOD_NAME) uninstall"
	$(call dirloop,uninstall)
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)
	rm -f $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME)

clean:
	rm -f VERSION
	$(call dirloop,clean)

.PHONY: all install uninstall clean test VERSIONFILE
