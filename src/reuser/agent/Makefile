# SPDX-FileCopyrightText: © 2014 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

COPY = reuser.php version.php ReuserAgent.php
WRAP = reuser
MODNAME = reuser

all: version.php reuser

version.php: version-process_php

# include the preprocessing stuff
include $(TOP)/Makefile.process

reuser:
	@echo "making locally runnable reuser (only for testing)"
	$(MAKE) -C $(FOCLIDIR) fo_wrapper
	ln -sf $(FOCLIDIR)/fo_wrapper.php reuser

install: all
	$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/reuser/agent/
	for file in $(COPY); do \
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
	rm -f *.o core version.php

.PHONY: all install uninstall clean

include $(DEPS)
