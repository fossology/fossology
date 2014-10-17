# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = ninka

DIRS = agent ui
TESTDIR = agent_tests
NINKA_DIR = ninka-tool

DIR_LOOP = @set -e; for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1); done

all: VERSIONFILE ninka-all
	$(call DIR_LOOP, )

test: all
	$(MAKE) -C $(TESTDIR) test

coverage: all
	$(MAKE) -C $(TESTDIR) coverage

VERSIONFILE:
	$(call WriteVERSIONFile,$(MOD_NAME))

install: all ninka-install
	$(call DIR_LOOP,install)
	$(INSTALL_DATA) VERSION $(DESTDIR)$(MODDIR)/$(MOD_NAME)/VERSION
	$(INSTALL_DATA) $(MOD_NAME).conf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_NAME).conf
	mkdir -p $(DESTDIR)$(SYSCONFDIR)/mods-enabled
	if test ! -e $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME); then \
		ln -s $(MODDIR)/$(MOD_NAME) $(DESTDIR)$(SYSCONFDIR)/mods-enabled; \
	fi

uninstall: ninka-uninstall
	$(call DIR_LOOP,uninstall)
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)
	rm -f $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME)

clean: ninka-clean
	$(call DIR_LOOP,clean)
	rm -f VERSION

ninka-all: ninka-Makefile
	$(MAKE) -C $(NINKA_DIR) all

ninka-install: ninka-Makefile
	$(MAKE) -C $(NINKA_DIR) install

ninka-uninstall: ninka-Makefile
	$(MAKE) -C $(NINKA_DIR) uninstall

ninka-clean: ninka-Makefile
	$(MAKE) -C $(NINKA_DIR) clean

ninka-Makefile:
	if [ ! -f $(NINKA_DIR)/Makefile ]; then \
		cd $(NINKA_DIR); perl Makefile.PL; \
	fi

.PHONY: all test coverage VERSIONFILE install uninstall clean
.PHONY: ninka-all ninka-install ninka-uninstall ninka-clean ninka-Makefile
