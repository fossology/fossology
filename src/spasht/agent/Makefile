# SPDX-FileCopyrightText: © 2019 Vivek Kumar <vvksindia@gmail.com>
# Author: Vivek Kumar<vvksindia@gmail.com>

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

MOD_NAME = spasht
COPY = spasht.php version.php SpashtAgent.php
WRAP = spasht

all: version.php spasht

version.php: version-process_php

# include the preprocessing stuff
include $(TOP)/Makefile.process

spasht:
	@echo "making locally runnable spasht (only for testing)"
	$(MAKE) -C $(FOCLIDIR) fo_wrapper
	ln -sf $(FOCLIDIR)/fo_wrapper.php $(WRAP)

install: all
	$(INSTALL_PROGRAM) -d $(DESTDIR)$(MODDIR)/$(MOD_NAME)/agent/
	for file in $(COPY); do \
		echo "installing $$file"; \
		$(INSTALL_DATA) $$file $(DESTDIR)$(MODDIR)/$(MOD_NAME)/agent/$$file; \
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
	rm -f $(WRAP) version.php

.PHONY: all install uninstall clean

include $(DEPS)
