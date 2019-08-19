######################################################################
# Copyright (C) 2019.
# Author: Vivek Kumar<vvksindia@gmail.com>
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
######################################################################

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
