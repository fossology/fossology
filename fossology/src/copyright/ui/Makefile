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
TOP=../../..
include $(TOP)/Makefile.conf

all:
	@echo "make copyright ui"

install:
	echo "make copyright ui install"
	mkdir -p $(DESTDIR)$(WEBDIR)/ui/copyright/
	$(INSTALL_DATA) library.php $(DESTDIR)$(WEBDIR)/ui/copyright/library.php
	$(INSTALL_DATA) hist.php $(DESTDIR)$(WEBDIR)/ui/copyright/hist.php
	$(INSTALL_DATA) list.php $(DESTDIR)$(WEBDIR)/ui/copyright/list.php
	$(INSTALL_DATA) view.php $(DESTDIR)$(WEBDIR)/ui/copyright/view.php
	$(INSTALL_DATA) agent.php $(DESTDIR)$(WEBDIR)/ui/copyright/agent.php
	$(INSTALL_DATA) oneshot.php $(DESTDIR)$(WEBDIR)/ui/copyright/oneshot.php

uninstall:
	echo "make copyright uninstall"
	rm -rf $(DESTDIR)$(WEBDIR)/ui/copyright/

.PHONY: install uninstall srcinstall srcuninstall
