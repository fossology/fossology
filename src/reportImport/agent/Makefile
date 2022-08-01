# SPDX-FileCopyrightText: © 2014-2016 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

MODNAME = reportImport

EXE = *.php
WRAP = reportImport
all: version.php reportImport

version.php: version-process_php

# include the preprocessing stuff
include $(TOP)/Makefile.process

reportImport:
	@echo "making locally runnable decider (only for testing)"
	$(MAKE) -C $(FOCLIDIR) fo_wrapper
	ln -sf $(FOCLIDIR)/fo_wrapper.php reportImport

services.xml:
	$(MAKE) -C $(FOLIBPHPDIR) services.xml

install: all
	$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/$(MODNAME)/agent/
	for file in $(EXE); do \
		echo "installing $$file"; \
		$(INSTALL_DATA) $$file $(DESTDIR)$(MODDIR)/$(MODNAME)/agent/$$file; \
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
	rm -f version.php $(WRAP)

.PHONY: all install uninstall clean

include $(DEPS)
