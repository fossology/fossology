# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

MODNAME = deciderjob

EXE = deciderjob.php version.php DeciderJobAgent.php
WRAP = deciderjob

all: version.php deciderjob

version.php: version-process_php

# include the preprocessing stuff
include $(TOP)/Makefile.process

deciderjob: services.xml
	@echo "making locally runnable deciderjob (only for testing)"
	$(MAKE) -C $(FOCLIDIR) fo_wrapper
	ln -sf $(FOCLIDIR)/fo_wrapper.php deciderjob

services.xml:
	$(MAKE) -C $(FOLIBPHPDIR) services.xml

install: all
	$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/$(MODNAME)/agent/
	for file in $(EXE); do \
		echo "installing $$file"; \
		$(INSTALL_PROGRAM) $$file $(DESTDIR)$(MODDIR)/$(MODNAME)/agent/$$file; \
	done
	for file in $(WRAP); do \
		echo "Making wrapper for $$file"; \
		ln -sf $(LIBEXECDIR)/fo_wrapper  $(DESTDIR)$(MODDIR)/$(MODNAME)/agent/$$file; \
	done

uninstall:
	for file in $(WRAP); do \
		rm -rf $(DESTDIR)$(MODDIR)/$(MODNAME)/agent/$$file; \
	done

clean:
	rm -f *.o core version.php $(WRAP)

.PHONY: all install uninstall clean

include $(DEPS)
