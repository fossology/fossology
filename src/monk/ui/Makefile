# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

all:
	@echo "make monk ui"

install:
	@echo "make monk ui install"
	mkdir -p $(DESTDIR)$(MODDIR)/monk/ui
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/monk/ui/

uninstall:
	@echo "make monk ui uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/monk/ui

clean:
	@echo "make monk ui clean"

.PHONY: all install uninstall clean
