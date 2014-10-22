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
MOD_NAMES=copyright ip ecc
MOD_SUBDIR=ui
TEMPL8FILES=`find template/ -type f`

all:
	@echo "make $(MOD_NAMES) ui"

install:
	for MOD_NAME in $(MOD_NAMES); do \
		echo "make $$MOD_NAME ui install"; \
		mkdir -p $(DESTDIR)$(MODDIR)/$$MOD_NAME/$(MOD_SUBDIR); \
		$(INSTALL_DATA) ./*-$$MOD_NAME.php $(DESTDIR)$(MODDIR)/$$MOD_NAME/$(MOD_SUBDIR); \
	done
	#TODO deduplicate php files
	#TODO this is broken but currently works
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/copyright/$(MOD_SUBDIR)
	rm $(DESTDIR)$(MODDIR)/copyright/$(MOD_SUBDIR)/agent-ecc.php
	rm $(DESTDIR)$(MODDIR)/copyright/$(MOD_SUBDIR)/agent-ip.php
	# mkdir -p $(DESTDIR)$(MODDIR)/$$MOD_NAME/$(MOD_SUBDIR)/template; \
	for file in $(TEMPL8FILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(MODDIR)/copyright/$(MOD_SUBDIR)/$$file ; \
	done

uninstall:
	for MOD_NAME in $(MOD_NAMES); do \
	echo "make $$MOD_NAME ui uninstall"; \
	rm -rf $(DESTDIR)$(MODDIR)/$$MOD_NAME/$(MOD_SUBDIR); \
	done

clean:
	echo "make $(MON_NAMES) ui clean"

.PHONY: install uninstall  all clean
