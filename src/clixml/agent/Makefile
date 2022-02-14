# Copyright Siemens AG 2021
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

MOD_NAME=clixml
MOD_SUBDIR=agent


EXE=clixml.php version.php services.php

all: version.php clixml

version.php: version-process_php

# include the preprocessing stuff
include $(TOP)/Makefile.process

clixml:
	@echo "making locally runnable report (only for testing)"
	$(MAKE) -C $(FOCLIDIR) fo_wrapper
	ln -sf $(FOCLIDIR)/fo_wrapper.php $(MOD_NAME)

install: all
	$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/
	for file in $(EXE); do \
		echo "installing $$file"; \
		$(INSTALL_PROGRAM) $$file $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/$$file; \
	done
	for file in $(MOD_NAME); do \
		echo "Making wrapper for $$file"; \
		ln -sf $(LIBEXECDIR)/fo_wrapper  $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/$$file; \
	done
	find template/ -type f -exec $(INSTALL_DATA) {} $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/{} \;

uninstall:
	for file in $(MOD_NAME); do \
		rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/$$file; \
	done

clean:
	rm -f core version.php $(MOD_NAME)

.PHONY: all install uninstall clean

include $(DEPS)
