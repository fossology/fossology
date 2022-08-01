# SPDX-FileCopyrightText: © 2014 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = spdx2
MOD_SUBDIR = ui
GENERAL_FILES = SpdxTwoAgentPlugin.php SpdxTwoGeneratorUi.php
MOD_NAMES = spdx2 spdx2tv dep5 spdx2csv

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
	$(INSTALL_DATA) DepFiveAgentPlugin.php $(DESTDIR)$(MODDIR)/dep5/$(MOD_SUBDIR)
	$(INSTALL_DATA) SpdxTwoTagValueAgentPlugin.php $(DESTDIR)$(MODDIR)/spdx2tv/$(MOD_SUBDIR)
	$(INSTALL_DATA) SpdxTwoCommaSeparatedValuesAgentPlugin.php $(DESTDIR)$(MODDIR)/spdx2csv/$(MOD_SUBDIR)

uninstall:
	for MOD in $(MOD_NAMES); do \
		rm -rf $(DESTDIR)$(MODDIR)/$$MOD/$(MOD_SUBDIR); \
	done


clean:
	@echo "nothing to do"

.PHONY: all install uninstall clean
