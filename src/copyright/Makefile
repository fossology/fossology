# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)
MOD_NAMES = copyright ip ecc

DIRS = agent ui
TESTDIR = agent_tests

dirloop=for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1);  done

all: VERSIONFILE
	$(call dirloop, )

coverage: all
	$(MAKE) -C $(TESTDIR)/Unit coverage

test: all
	$(MAKE) -C $(TESTDIR) test

VERSIONFILE:
	for MOD_NAME in $(MOD_NAMES); do \
		$(call WriteVERSIONFile,$$MOD_NAME); \
		mv -f VERSION VERSION-$$MOD_NAME; \
	done

install: all
	$(call dirloop,install);
	for MOD_NAME in $(MOD_NAMES); do \
	echo "make $$MOD_NAME install"; \
	$(INSTALL_DATA) VERSION-$$MOD_NAME $(DESTDIR)$(MODDIR)/$$MOD_NAME/VERSION; \
	$(INSTALL_DATA) $$MOD_NAME.conf $(DESTDIR)$(MODDIR)/$$MOD_NAME/$$MOD_NAME.conf; \
	mkdir -p $(DESTDIR)$(SYSCONFDIR)/mods-enabled; \
	if test ! -e $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$$MOD_NAME; then \
	  ln -s $(MODDIR)/$$MOD_NAME $(DESTDIR)$(SYSCONFDIR)/mods-enabled ; fi; \
	done


uninstall:
	$(call dirloop,uninstall)
	for MOD_NAME in $(MON_NAMES); do \
	echo "make $$MOD_NAME uninstall"; \
	rm -rf $(DESTDIR)$(MODDIR)/$$MOD_NAME; \
	rm -f $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$$MOD_NAME; \
	done


clean:
	rm -f VERSION*
	$(call dirloop,clean)

.PHONY: all install uninstall clean test VERSIONFILE
