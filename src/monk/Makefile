# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME=monk

DIRS = agent ui agent_tests
TESTDIR = agent_tests

dirloop=for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1);  done
		 
all: VERSIONFILE
	$(call dirloop, )

coverage: all
	$(MAKE) -C $(TESTDIR)/Unit coverage

test: all
	$(MAKE) -C $(TESTDIR) test

# generate the VERSION file
VERSIONFILE:
	$(call WriteVERSIONFile, "monk")

install: all
	@echo "make $(MOD_NAME) install"
	$(call dirloop,install)
	$(INSTALL_DATA) VERSION $(DESTDIR)$(MODDIR)/$(MOD_NAME)/VERSION
	$(INSTALL_DATA) $(MOD_NAME).conf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_NAME).conf


uninstall:
	@echo "make $(MOD_NAME) uninstall"
	$(call dirloop,uninstall)
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)
	rm -f $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME)


clean:
	$(call dirloop,clean)
	rm -f VERSION

.PHONY: all install uninstall clean test VERSIONFILE
