# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME=decider

DIRS = agent ui
TESTDIR =

DIR_LOOP = @for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1); done

all: VERSIONFILE
	$(call DIR_LOOP, )

coverage: all
	@echo "no coverage for $(MOD_NAME)"

test: all
	$(MAKE) -C $(TESTDIR) test

VERSIONFILE:
	$(call WriteVERSIONFile,$(MOD_NAME))

install: all
	@echo "make $(MOD_NAME) install"
	$(call DIR_LOOP,install)
	@mkdir -p $(DESTDIR)$(SYSCONFDIR)/mods-enabled
	@if test ! -e $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME); then \
		ln -s $(MODDIR)/$(MOD_NAME) $(DESTDIR)$(SYSCONFDIR)/mods-enabled ; fi;
	$(INSTALL_DATA) VERSION $(DESTDIR)$(MODDIR)/$(MOD_NAME)/VERSION
	$(INSTALL_DATA) $(MOD_NAME).conf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_NAME).conf


uninstall:
	@echo "make $(MOD_NAME) uninstall"
	$(call DIR_LOOP,uninstall)
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)
	rm -f $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME)


clean:
	$(call DIR_LOOP,clean)
	rm -f VERSION

.PHONY: all install uninstall clean test VERSIONFILE
