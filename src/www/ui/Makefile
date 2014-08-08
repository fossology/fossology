######################################################################
# Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.
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
# www/ui makefile
#
TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)
CONFDIR = $(DESTDIR)$(SYSCONFDIR)

DIRS=css images locale scripts ajaxPHP

UIFILES=`find . -type f | grep -v svn |grep -v tests | grep -E "(php|dat|dtd|js)$$" `
OBSOLETEFILES = admin-tag-ns.php admin-change-owner.php admin-tag-ns-perm.php \
                group-manage-self.php group-manage.php ajax-perms.php

OTHERFILES=`find . -type f | grep -v svn |grep -v tests | grep -E "(css|htc|gif|png)$$" `

RMFILES=FILES=`find . -type f | grep -v svn |grep -v tests | grep -E "(php|css|htm|html|dtd|htc|gif|png|dat|po|mo)$$"`

all:

install: all

	mkdir -p $(DESTDIR)$(WEBDIR)/ui; \
	for dir in $(DIRS); do \
		mkdir -p $(DESTDIR)$(WEBDIR)/ui/$$dir; \
	done

	for file in $(OBSOLETEFILES); do \
	  rm -f $(DESTDIR)$(WEBDIR)/ui/$$file; \
	  rm -f $$file; \
	done
	for file in $(UIFILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(WEBDIR)/ui/$$file; \
	done
	for file in $(OTHERFILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(WEBDIR)/ui/$$file; \
	done
  
	@echo $(SYSCONFDIR) > $(DESTDIR)$(WEBDIR)/ui/fossology.rc
	@mkdir -p $(CONFDIR)/mods-enabled
	@if test ! -e $(CONFDIR)/mods-enabled/www; then \
	  ln -s $(MODDIR)/www $(CONFDIR)/mods-enabled/ ; fi;

uninstall:
	@rm -rf $(DESTDIR)$(WEBDIR)
	@rm -f $(CONFDIR)/mods-enabled/www

clean:
	rm -f *.o core $(iPATHFILE)

.PHONY: all install uninstall clean test
