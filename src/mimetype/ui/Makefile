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

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = mimetype
MOD_SUBDIR = ui

all:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) $@"
	@echo "nothing to do"

install:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) $@"
	mkdir -p $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

uninstall:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) $@"
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

clean:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) $@"
	@echo "nothing to do"

.PHONY: all install uninstall clean
