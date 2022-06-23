# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAMES = copyright ecc keyword
MOD_SUBDIR = ui

all:
	@echo "nothing to do"

install:
	@for MOD_NAME in $(MOD_NAMES); do \
		echo "make $$MOD_NAME ui $@"; \
		mkdir -p $(DESTDIR)$(MODDIR)/$$MOD_NAME/$(MOD_SUBDIR); \
		$(INSTALL_DATA) ./*-$$MOD_NAME.php $(DESTDIR)$(MODDIR)/$$MOD_NAME/$(MOD_SUBDIR); \
	done
	#TODO deduplicate php files
	#TODO this is broken but currently works
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/copyright/$(MOD_SUBDIR)
	rm $(DESTDIR)$(MODDIR)/copyright/$(MOD_SUBDIR)/agent-ecc.php
	rm $(DESTDIR)$(MODDIR)/copyright/$(MOD_SUBDIR)/agent-keyword.php
	find template/ -type f -exec $(INSTALL_DATA) {} $(DESTDIR)$(MODDIR)/copyright/$(MOD_SUBDIR)/{} \;

uninstall:
	@for MOD_NAME in $(MOD_NAMES); do \
		echo "make $$MOD_NAME ui $@"; \
		rm -rf $(DESTDIR)$(MODDIR)/$$MOD_NAME/$(MOD_SUBDIR); \
	done

clean:
	@echo "nothing to do"

.PHONY: all install uninstall clean
