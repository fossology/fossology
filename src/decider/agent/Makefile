# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

EXE=decider.php version.php
WRAP=decider

all: version.php

version.php: version-process_php

# include the preprocessing stuff
include $(TOP)/Makefile.process

install: all
	$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/decider/agent/
	for file in $(EXE); do \
		echo "installing $$file"; \
		$(INSTALL_PROGRAM) $$file $(DESTDIR)$(LIBEXECDIR)/$$file; \
	done
	for file in $(WRAP); do \
		echo "Making wrapper for $$file"; \
		ln -sf $(LIBEXECDIR)/fo_wrapper  $(DESTDIR)$(MODDIR)/decider/agent/$$file; \
	done

uninstall:
	for file in $(WRAP); do \
	  rm -rf $(DESTDIR)$(MODDIR)/decider/agent/$$file; \
	done

clean:
	rm -f *.o core version.php $(WRAP)

.PHONY: all install uninstall clean

include $(DEPS)
