# SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = delagent
MOD_SUBDIR = ui

OBSOLETEFILES = admin-upload-delete.php
UIFILES = `find . -type f | grep -v svn |grep -v tests | grep -E "(php|dat|dtd|js|html|twig)$$"`
DIRS = Page template
DIR_LOOP = @set -e; for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1); done

all:
	@echo "nothing to do"

install:
	mkdir -p $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	@for dir in $(DIRS); do \
		mkdir -p $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/$$dir; \
	done
	@for file in $(UIFILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/$$file; \
	done
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	@for file in $(OBSOLETEFILES); do \
		rm -f $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/$$file; \
		rm -f $$file; \
	done
	mkdir -p $(DESTDIR)$(SYSCONFDIR)/mods-enabled
	ln -sfn $(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/Page $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME)-page

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	rm -f $(DESTDIR)$(SYSCONFDIR)/mods-enabled/$(MOD_NAME)-page
clean:
	@echo "nothing to do"

.PHONY: all install uninstall clean
