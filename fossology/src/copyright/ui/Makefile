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
	mkdir -p $(DESTDIR)$(WEBDIR)/copyright/
	$(INSTALL_DATA) library.php $(DESTDIR)$(WEBDIR)/copyright/library.php
	$(INSTALL_DATA) hist.php $(DESTDIR)$(WEBDIR)/copyright/hist.php
	$(INSTALL_DATA) list.php $(DESTDIR)$(WEBDIR)/copyright/list.php
	$(INSTALL_DATA) view.php $(DESTDIR)$(WEBDIR)/copyright/view.php
	$(INSTALL_DATA) agent.php $(DESTDIR)$(WEBDIR)/copyright/agent.php
	$(INSTALL_DATA) oneshot.php $(DESTDIR)$(WEBDIR)/copyright/oneshot.php


# have to create a version of copyright.php to include from the source
	#sed -e "1,\$$s/\$$WEBDIR\.'\/plugins\//'/" copyright.php > ../../../ui/plugins/copyright.php$
	echo "copying to ../../www/ui/copyright"
	if [ -d ../../www/ui/copyright ]; then \
		cp library.php ../../www/ui/copyright/library.php; \
		cp hist.php ../../www/ui/copyright/hist.php; \
		cp list.php ../../www/ui/copyright/list.php; \
		cp view.php ../../www/ui/copyright/view.php; \
		cp agent.php ../../www/ui/copyright/agent.php; \
		cp oneshot.php ../../www/ui/copyright/oneshot.php; \
	else \
		@mkdir ../../www/ui/copyright; \
		cp library.php ../../www/ui/copyright/library.php; \
		cp hist.php ../../www/ui/copyright/hist.php; \
		cp list.php ../../www/ui/copyright/list.php; \
		cp view.php ../../www/ui/copyright/view.php; \
		cp agent.php ../../www/ui/copyright/agent.php; \
		cp oneshot.php ../../www/ui/copyright/oneshot.php; \
	fi

uninstall:
	echo "make copyright uninstall"
#	rm -f $(DESTDIR)$(WEBDIR)/copyright.php
	rm -rf $(DESTDIR)$(WEBDIR)/copyright/
	@rm -rf ../../www/ui/copyright
#	@if [ -d $(DESTDIR)$(WEBDIR)/plugins ]; then \
   	   if [ "`ls -A $(DESTDIR)$(WEBDIR)/plugins`" ]; then \
	      echo "WARNING: $(DESTDIR)$(WEBDIR)/plugins not empty, not removing";\
           else \
              rmdir $(DESTDIR)$(WEBDIR)/plugins; \
           fi; \
        fi

srcuninstall:
#	rm -f ../../../ui/plugins/copyright.php
	rm -rf ../../www/ui/copyright/
#	@if [ -d ../../../ui/gins ]; then \
   	   if [ "`ls -A ../../../ui/plugins`" ]; then \
	      echo "WARNING: ../../../ui/plugins not empty, not removing";\
           else \
              rmdir ../../../ui/plugins; \
           fi; \
        fi
   
.PHONY: install uninstall srcinstall srcuninstall
