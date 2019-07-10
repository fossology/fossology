# Copyright 2019
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

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
	$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/softwareHeritage/agent/
	for file in $(COPY); do \
		echo "installing $$file"; \
		$(INSTALL_PROGRAM) $$file $(DESTDIR)$(MODDIR)/softwareHeritage/agent/$$file; \
	done
	for file in $(WRAP); do \
		echo "Making wrapper for $$file"; \
		ln -sf $(LIBEXECDIR)/fo_wrapper  $(DESTDIR)$(MODDIR)/softwareHeritage/agent/$$file; \
	done

uninstall:
	for file in $(WRAP); do \
		rm -rf $(DESTDIR)$(MODDIR)/softwareHeritage/agent/$$file; \
	done

clean:
	rm -f *.o core version.php

.PHONY: all install uninstall clean

include $(DEPS)
