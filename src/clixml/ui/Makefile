# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = clixml
MOD_SUBDIR = ui
GENERAL_FILES = CliXmlAgentPlugin.php CliXmlGeneratorUi.php
MOD_NAMES = clixml

all:
	@echo "nothing to do"

install:
	mkdir -p $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	for file in $(GENERAL_FILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR); \
	done
	for MOD in $(MOD_NAMES); do \
		mkdir -p $(DESTDIR)$(MODDIR)/$$MOD/$(MOD_SUBDIR); \
	done

uninstall:
	for MOD in $(MOD_NAMES); do \
		rm -rf $(DESTDIR)$(MODDIR)/$$MOD/$(MOD_SUBDIR); \
	done


clean:
	@echo "nothing to do"

.PHONY: all install uninstall clean
