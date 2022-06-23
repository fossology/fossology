# SPDX-FileCopyrightText: © Fossology contributors

# SPDX-License-Identifier: FSFAP

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

MOD_NAME = softwareHeritage

COPY = softwareHeritage.php version.php softwareHeritageAgent.php
WRAP = softwareHeritage

all: version.php softwareHeritage

version.php: version-process_php

# include the preprocessing stuff
include $(TOP)/Makefile.process

softwareHeritage:
	@echo "making locally runnable softwareHeritage (only for testing)"
	$(MAKE) -C $(FOCLIDIR) fo_wrapper
	ln -sf $(FOCLIDIR)/fo_wrapper.php softwareHeritage

install: all
	$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/$(MOD_NAME)/agent/
	for file in $(COPY); do \
		echo "installing $$file"; \
		$(INSTALL_PROGRAM) $$file $(DESTDIR)$(MODDIR)/$(MOD_NAME)/agent/$$file; \
	done
	for file in $(WRAP); do \
		echo "Making wrapper for $$file"; \
		ln -sf $(LIBEXECDIR)/fo_wrapper  $(DESTDIR)$(MODDIR)/$(MOD_NAME)/agent/$$file; \
	done

uninstall:
	for file in $(WRAP); do \
		rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/agent/$$file; \
	done

clean:
	rm -f *.o core version.php

.PHONY: all install uninstall clean

include $(DEPS)
