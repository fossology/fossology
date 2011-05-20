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
	echo "make copyright"

install:
	echo "make copyright install"
	mkdir -p $(DESTDIR)$(WEBDIR)/plugins/
	mkdir -p $(DESTDIR)$(WEBDIR)/plugins/copyright/
	$(INSTALL_DATA) copyright.php $(DESTDIR)$(WEBDIR)/plugins/copyright.php
	$(INSTALL_DATA) library.php $(DESTDIR)$(WEBDIR)/plugins/copyright/library.php
	$(INSTALL_DATA) hist.php $(DESTDIR)$(WEBDIR)/plugins/copyright/hist.php
	$(INSTALL_DATA) list.php $(DESTDIR)$(WEBDIR)/plugins/copyright/list.php
	$(INSTALL_DATA) view.php $(DESTDIR)$(WEBDIR)/plugins/copyright/view.php
	$(INSTALL_DATA) agent.php $(DESTDIR)$(WEBDIR)/plugins/copyright/agent.php
	$(INSTALL_DATA) oneshot.php $(DESTDIR)$(WEBDIR)/plugins/copyright/oneshot.php

	if [ ! -d $(DESTDIR)$(WEBDIR)/plugins/simpleUi ] ; \
		then \
         mkdir -p $(DESTDIR)$(WEBDIR)/plugins/simpleUi; \
         cd $(DESTDIR)$(WEBDIR)/plugins/simpleUi; \
      if [ ! -h 'copyright.php' ] ; \
      then \
         ln -s ../copyright.php copyright.php; \
         echo "Copyright link created"; \
      fi \
   else \
         echo "Did not find $(DESTDIR)$(WEBDIR)/plugins/simpleUi"; \
         echo "Could not create symlink in plugins/simpleUi"; \
         echo "As root, create the symlink by hand."; \
   fi
	
srcinstall:
	mkdir ../../../ui/plugins/copyright
# have to create a version of copyright.php to include from the source
	sed -e "1,\$$s/\$$WEBDIR\.'\/plugins\//'/" copyright.php > ../../../ui/plugins/copyright.php
#	cp copyright.php ../../../ui/plugins/copyright.php
	cp library.php ../../../ui/plugins/copyright/library.php
	cp hist.php ../../../ui/plugins/copyright/hist.php
	cp list.php ../../../ui/plugins/copyright/list.php
	cp view.php ../../../ui/plugins/copyright/view.php
	cp agent.php ../../../ui/plugins/copyright/agent.php
	cp oneshot.php ../../../ui/plugins/copyright/oneshot.php

uninstall:
	echo "make copyright uninstall"
	rm -f $(DESTDIR)$(WEBDIR)/plugins/copyright.php
	rm -rf $(DESTDIR)$(WEBDIR)/plugins/copyright/
	@if [ -d $(DESTDIR)$(WEBDIR)/plugins ]; then \
   	   if [ "`ls -A $(DESTDIR)$(WEBDIR)/plugins`" ]; then \
	      echo "WARNING: $(DESTDIR)$(WEBDIR)/plugins not empty, not removing";\
           else \
              rmdir $(DESTDIR)$(WEBDIR)/plugins; \
           fi; \
        fi

srcuninstall:
	rm -f ../../../ui/plugins/copyright.php
	rm -rf ../../../ui/plugins/copyright/
	@if [ -d ../../../ui/plugins ]; then \
   	   if [ "`ls -A ../../../ui/plugins`" ]; then \
	      echo "WARNING: ../../../ui/plugins not empty, not removing";\
           else \
              rmdir ../../../ui/plugins; \
           fi; \
        fi
   
.PHONY: install uninstall srcinstall srcuninstall
