# SPDX-FileCopyrightText: © 2021 Orange
# Author: Bartłomiej Dróżdż <bartlomiej.drozdz@orange.com>

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

MOD_NAME = reso

COPY = reso.php version.php ResoAgent.php
WRAP = reso

all: version.php reso

version.php: version-process_php

# include the preprocessing stuff
include $(TOP)/Makefile.process

reso:
	@echo "making locally runnable reso (only for testing)"
	$(MAKE) -C $(FOCLIDIR) fo_wrapper
	ln -sf $(FOCLIDIR)/fo_wrapper.php reso

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
	rm -f version.php $(WRAP)

.PHONY: all install uninstall clean

include $(DEPS)
