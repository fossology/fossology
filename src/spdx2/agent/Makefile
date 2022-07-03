# SPDX-FileCopyrightText: © 2015 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

MOD_NAME = spdx2
MOD_SUBDIR = agent
MOD_NAMES = spdx2 spdx2tv dep5 spdx2csv

EXE=spdx2.php version.php services.php spdx2utils.php

all: version.php spdx2

version.php: version-process_php

# include the preprocessing stuff
include $(TOP)/Makefile.process

spdx2:
	@echo "making locally runnable report (only for testing)"
	$(MAKE) -C $(FOCLIDIR) fo_wrapper
	ln -sf $(FOCLIDIR)/fo_wrapper.php $(MOD_NAME)

install: all
	for MOD in $(MOD_NAMES); do \
		$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/$$MOD/$(MOD_SUBDIR)/; \
	done
	for file in $(EXE); do \
		echo "installing $$file"; \
		$(INSTALL_DATA) $$file $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/$$file; \
	done
	echo "Making wrapper for $(MOD_NAME)"
	ln -sf $(LIBEXECDIR)/fo_wrapper  $(DESTDIR)$(MODDIR)/$(MOD_NAME)/agent/$(MOD_NAME)
	find template/ -type f -exec $(INSTALL_DATA) {} $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/{} \;
	for MOD in $(MOD_NAMES); do \
		$(INSTALL_PROGRAM) agent.sh $(DESTDIR)$(MODDIR)/$$MOD/$(MOD_SUBDIR)/$$MOD.sh; \
	done

uninstall:
	for MOD in $(MOD_NAMES); do \
		rm -rf $(DESTDIR)$(MODDIR)/$$MOD/$(MOD_SUBDIR); \
	done

clean:
	rm -f core version.php $(MOD_NAME)

.PHONY: all install uninstall clean

include $(DEPS)
