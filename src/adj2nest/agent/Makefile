######################################################################
# Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
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
CONFDIR = $(DESTDIR)$(SYSCONFDIR)/fossology
MODULEDIR = $(shell dirname `pwd`)

CFLAGS_LOCAL= $(ALL_CFLAGS)
EXE=adj2nest

all: $(EXE)

$(EXE): %: %.c $(DB) $(REPO) $(VARS) $(FOLIB)
	$(CC) $< $(CFLAGS_LOCAL) -o $@

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)
	@if test ! -e $(CONFDIR)/mods-enabled/$(EXE); then \
	  ln -s $(MODULEDIR) $(CONFDIR)/mods-enabled ; fi;

uninstall:
	@rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)
	@rm -f $(CONFDIR)/mods-enabled/$(EXE)


test: all
	@echo "*** No tests available for src/$(EXE) ***"

coverage:
	@echo "NOTICE: no coverage available for adj2nest"

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean test
