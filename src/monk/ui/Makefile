# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = monk
MOD_SUBDIR = ui

all:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) $@"
	@echo "nothing to do"

install:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) $@"
	mkdir -p $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

uninstall:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) $@"
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

clean:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) $@"
	@echo "nothing to do"

.PHONY: all install uninstall clean
