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
TOP = ../../..
include $(TOP)/Makefile.conf

all:
	@echo "make nomos ui"

install:
	@echo "make nomos ui install"
	mkdir -p $(DESTDIR)$(MODDIR)/nomos/ui
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/nomos/ui/

uninstall:
	@echo "make nomos ui uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/nomos/ui

clean:
	@echo "make nomos ui clean"

.PHONY: all install uninstall clean
