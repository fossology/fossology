# SPDX-FileCopyrightText: © 2014-2015 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

MODNAME = decider

EXE = decider.php version.php DeciderAgent.php BulkReuser.php
WRAP = decider
SCRIPT = copyrightDeactivationClutterRemovalScript.py

all: version.php decider

version.php: version-process_php

# include the preprocessing stuff
include $(TOP)/Makefile.process

decider: services.xml
	@echo "making locally runnable decider (only for testing)"
	$(MAKE) -C $(FOCLIDIR) fo_wrapper
	ln -sf $(FOCLIDIR)/fo_wrapper.php decider

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
	$(INSTALL_PROGRAM) $(SCRIPT) $(DESTDIR)$(MODDIR)/$(MODNAME)/agent/$(SCRIPT)

uninstall:
	for file in $(WRAP); do \
		rm -rf $(DESTDIR)$(MODDIR)/$(MODNAME)/agent/$$file; \
	done
	rm -rf 	$(DESTDIR)$(MODDIR)/$(MODNAME)/agent/$(SCRIPT)

clean:
	rm -f *.o core version.php $(WRAP)

.PHONY: all install uninstall clean

include $(DEPS)
