# SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = scancode
MOD_SUBDIR = ui

all:
	@echo "nothing to do"

install:
	mkdir -p $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	find template/ -type f -exec $(INSTALL_DATA) {} $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/{} \;
	
uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

clean:
	@echo "nothing to do"

.PHONY: all install uninstall clean
