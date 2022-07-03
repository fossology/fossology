# SPDX-FileCopyrightText: © 2017 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

EXE=unifiedreport.php reportStatic.php reportSummary.php version.php
WRAP=unifiedreport
MODNAME=unifiedreport

all: version.php

version.php: version-process_php

# include the preprocessing stuff
include $(TOP)/Makefile.process

unifiedreport: all
	@echo "making locally runnable report (only for testing)"
	$(MAKE) -C $(FOCLIDIR) fo_wrapper
	ln -sf $(FOCLIDIR)/fo_wrapper.php unifiedreport

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
	rm -f *.o core version.php $(WRAP)

.PHONY: all install uninstall clean

include $(DEPS)
