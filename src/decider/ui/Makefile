# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP=../../..
include $(TOP)/Makefile.conf

all:
	@echo "make decider ui"

install:
	@echo "make decider ui install"
	mkdir -p $(DESTDIR)$(MODDIR)/decider/ui
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/decider/ui/

uninstall:
	@echo "make decider ui uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/decider/ui

clean:
	@echo "make decider ui clean"

.PHONY: all install uninstall clean
