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
MOD_NAME=copyright
MOD_SUBDIR=ui

all:
	@echo "make copyright ui"

install:
	echo "make copyright ui install"
	mkdir -p $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	/bin/chown $(PROJECTUSER):$(PROJECTGROUP) $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	/bin/chown $(PROJECTUSER):$(PROJECTGROUP) $(DESTDIR)$(MODDIR)/$(MOD_NAME)/
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

uninstall:
	echo "make copyright ui uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

clean:
	echo "make copyright ui clean"

.PHONY: install uninstall  all clean
