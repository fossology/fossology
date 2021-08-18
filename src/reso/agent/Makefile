# SPDX-License-Identifier: GPL-2.0
# SPDX-FileCopyrightText: Copyright (c) 2021 Orange
# Author: Bartłomiej Dróżdż <bartlomiej.drozdz@orange.com>
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
