# SPDX-FileCopyrightText: © 2019 Vivek Kumar <vvksindia@gmail.com>
# Author: Vivek Kumar<vvksindia@gmail.com>

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = spasht
MOD_SUBDIR = ui

all:
	@echo "nothing to do"

install: all
	$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/template
	find template/ -type f -exec $(INSTALL_DATA) {} $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/{} \;

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

clean:
	@echo "nothing to do"

.PHONY: all install uninstall clean
