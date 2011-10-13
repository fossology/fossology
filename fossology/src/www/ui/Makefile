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
VARS=$(TOP)/Makefile.conf
include $(VARS)
CONFDIR = $(DESTDIR)$(SYSCONFDIR)

# also set in Makefile.conf, but we override with a shorter version
iPATHFILE=pathinclude.php

DIRS=css images locale

UIFILES=`find . -type f | grep -v svn |grep -v tests | grep -v simpleUi | grep -E "(php|dat|dtd)$$" | grep -v $(iPATHFILE)`

OTHERFILES=`find . -type f | grep -v svn |grep -v tests | grep -v simpleUi | grep -E "(css|htc|gif|png)$$" | grep -v $(iPATHFILE)`

RMFILES=FILES=`find . -type f | grep -v svn |grep -v tests | grep -E "(php|css|htm|html|dtd|htc|gif|png|dat|po|mo)$$"`

all: $(iPATHFILE)

$(iPATHFILE): $(VARS)
	@echo "Building $(iPATHFILE)"
	@echo "<?php" > $(iPATHFILE)
	@echo "\$$BINDIR=\"$(BINDIR)\";" >> $(iPATHFILE)
	@echo "\$$LIBDIR=\"$(LIBDIR)\";" >> $(iPATHFILE)
	@echo "\$$LIBEXECDIR=\"$(LIBEXECDIR)\";" >> $(iPATHFILE)
	@echo "\$$INCLUDEDIR=\"$(INCLUDEDIR)\";" >> $(iPATHFILE)
	@echo "\$$MAN1DIR=\"$(MAN1DIR)\";" >> $(iPATHFILE)
	@echo "\$$AGENTDIR=\"$(AGENTDIR)\";" >> $(iPATHFILE)
	@echo "\$$SYSCONFDIR=\"$(SYSCONFDIR)\";" >> $(iPATHFILE)
	@echo "\$$WEBDIR=\"$(WEBDIR)\";" >> $(iPATHFILE)
	@echo "\$$PHPDIR=\"$(PHPDIR)\";" >> $(iPATHFILE)
	@echo "\$$PROJECTSTATEDIR=\"$(PROJECTSTATEDIR)\";" >> $(iPATHFILE)
	@echo "\$$PROJECT=\"$(PROJECT)\";" >> $(iPATHFILE)
	@echo "\$$DATADIR=\"$(DATADIR)\";" >> $(iPATHFILE)
	@echo "\$$VERSION=\"$(VERSION)\";" >> $(iPATHFILE)
	@echo "\$$SVN_REV=\"$(SVN_REV)\";" >> $(iPATHFILE)
	@echo "\$$FOSRCDIR=\"$(FOSRCDIR)\";" >> $(iPATHFILE)
	@echo "?>" >> $(iPATHFILE)

install: all $(iPATHFILE)

	mkdir -p $(DESTDIR)$(WEBDIR)/ui; \
	for dir in $(DIRS); do \
		mkdir -p $(DESTDIR)$(WEBDIR)/ui/$$dir; \
	done

	for file in $(UIFILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(WEBDIR)/ui/$$file; \
	done
	for file in $(OTHERFILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(WEBDIR)/ui/$$file; \
	done

	$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(PHPDIR)/$(iPATHFILE)
	@if test ! -e $(CONFDIR)/mods-enabled/www; then \
	  ln -s $(MODDIR)/www/ui $(CONFDIR)/mods-enabled/www ; fi;

uninstall:
	for file in $(RMFILES); do \
		rm -f $(DESTDIR)$(WEBDIR)/ui/$$file; \
	done
	
	$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(PHPDIR)/$(iPATHFILE)
	
	for dir in $(DIRS); do \
		rmdir $(DESTDIR)$(WEBDIR)/ui/$$dir; \
	done

	@rm -f $(DESTDIR)$(WEBDIR)/ui
	@rm -f $(DESTDIR)$(PHPDIR)/$(iPATHFILE)
	@rm -f $(CONFDIR)/mods-enabled/www

clean:
	rm -f *.o core $(iPATHFILE)

.PHONY: all install uninstall srcinstall srcuninstall clean test
