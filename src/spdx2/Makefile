# SPDX-FileCopyrightText: © 2015 Siemens AG

# SPDX-License-Identifier: FSFAP
TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = spdx2
MOD_NAMES = spdx2 spdx2tv dep5 spdx2csv

DIRS = agent ui
TESTDIRS = agent_tests

DIR_LOOP = @set -e; for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1); done
TESTDIR_LOOP = @set -e; for dir in $(TESTDIRS); do $(MAKE) -s -C $$dir $(1); done

all: VERSIONFILE
	$(call DIR_LOOP, )

test: all
	$(call TESTDIR_LOOP,test)

coverage: all
	@echo "nothing to do"

VERSIONFILE:
	$(call WriteVERSIONFile,$(MOD_NAME))

install: all
	$(call DIR_LOOP,install)
	mkdir -p $(DESTDIR)$(SYSCONFDIR)/mods-enabled
	for MOD in $(MOD_NAMES); do \
		$(INSTALL_DATA) VERSION $(DESTDIR)$(MODDIR)/$$MOD/VERSION; \
		if test ! -e $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$$MOD; then \
			ln -s $(MODDIR)/$$MOD $(DESTDIR)$(SYSCONFDIR)/mods-enabled; \
		fi; \
		$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/$$MOD/; \
		$(INSTALL_DATA) VERSION $(DESTDIR)$(MODDIR)/$$MOD_NAME/VERSION; \
		$(INSTALL_DATA) $$MOD.conf $(DESTDIR)$(MODDIR)/$$MOD/$$MOD.conf; \
	done

uninstall:
	$(call DIR_LOOP,uninstall)
	for MOD in $(MOD_NAMES); do \
		rm -rf $(DESTDIR)$(MODDIR)/$$MOD; \
		rm -f $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$$MOD; \
	done

clean:
	$(call DIR_LOOP,clean)
	$(call TESTDIR_LOOP,clean)
	rm -f VERSION

.PHONY: all test coverage VERSIONFILE install uninstall clean
