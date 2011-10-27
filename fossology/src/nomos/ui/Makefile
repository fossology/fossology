######################################################################
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
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

#
# Makefile for noms/ui
TOP=../../..
include $(TOP)/Makefile.conf

all:
	@echo "make nomos ui"

install:
	echo "make nomos ui install"
	mkdir -p $(DESTDIR)$(MODDIR)/nomos/ui
	$(INSTALL_DATA) agent-nomos-once.php $(DESTDIR)$(MODDIR)/nomos/ui/agent-nomos-once.php
	$(INSTALL_DATA) agent-nomos.php $(DESTDIR)$(MODDIR)/nomos/ui/agent-nomos.php
	$(INSTALL_DATA) ajax-filelic.php $(DESTDIR)$(MODDIR)/nomos/ui/ajax-filelic.php
	$(INSTALL_DATA) nomos-diff.php $(DESTDIR)$(MODDIR)/nomos/ui/nomos-diff.php
	$(INSTALL_DATA) ui-list-lic-files.php $(DESTDIR)$(MODDIR)/nomos/ui/ui-list-lic-files.php
	$(INSTALL_DATA) ui-nomos-license-list.php $(DESTDIR)$(MODDIR)/nomos/ui/ui-nomos-license-list.php
	$(INSTALL_DATA) ui-nomos-license.php $(DESTDIR)$(MODDIR)/nomos/ui/ui-nomos-license.php

uninstall:
	echo "make nomos uninstall"
	rm -rf $(MODDIR)/nomos

.PHONY: install uninstall srcinstall srcuninstall
