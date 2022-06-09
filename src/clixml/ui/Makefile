# SPDX-FileCopyrightText: © 2021 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = clixml
MOD_SUBDIR = ui
GENERAL_FILES = CliXmlAgentPlugin.php CliXmlGeneratorUi.php

all:
	@echo "nothing to do"

install:
	mkdir -p $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	for file in $(GENERAL_FILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR); \
	done


uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)


clean:
	@echo "nothing to do"

.PHONY: all install uninstall clean
