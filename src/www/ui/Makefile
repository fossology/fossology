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

# also set in Makefile.conf, but we override with a shorter version
iPATHFILE=pathinclude.php

DIRS=images locale

all: $(iPATHFILE)

$(iPATHFILE): $(VARS)
	@echo "Building $(iPATHFILE)"
	@echo "<?php" > $(iPATHFILE)
	@echo "global \$$GlobalReady;" >> $(iPATHFILE)
	@echo "if (!isset(\$$GlobalReady)) { exit; }" >> $(iPATHFILE)
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

	for dir in $(DIRS); do \
		mkdir -p $(DESTDIR)$(WEBDIR)/$$dir; \
	done

	for file in $(FILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(WEBDIR)/$$file; \
	done

	$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(PHPDIR)/$(iPATHFILE)
	
	
uninstall:
	for file in $(FILES); do \
		rm -f $(DESTDIR)$(WEBDIR)/$$file; \
	done
	
	$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(PHPDIR)/$(iPATHFILE)
	
	cd $(DESTDIR)$(WEBDIR)/plugins/simpleUi; \
	for link in $(RMLINKS); do \
		rm -f $$link; \
	done
	
	for dir in $(DIRS); do \
		rmdir $(DESTDIR)$(WEBDIR)/$$dir; \
	done

	rm -f $(DESTDIR)$(PHPDIR)/$(iPATHFILE)
	

clean:
	rm -f *.o core $(iPATHFILE)

.PHONY: all install uninstall srcinstall srcuninstall clean test
