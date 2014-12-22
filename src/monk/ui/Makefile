# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_SUBDIR = ui

MONK_PHPS = agent-monk.php admin-monk-revision.php
BULK_PHPS = agent-monk-bulk.php

all:
	@echo "nothing to do"

install: install-monk install-monkbulk

install-monk: MOD_NAME = monk
install-monk:
	mkdir -p $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	$(INSTALL_DATA) $(MONK_PHPS) $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

install-monkbulk: MOD_NAME = monkbulk
install-monkbulk:
	mkdir -p $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	$(INSTALL_DATA) $(BULK_PHPS) $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

clean:
	@echo "nothing to do"

.PHONY: all install uninstall clean
