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

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

CONFDIR = $(DESTDIR)$(SYSCONFDIR)
CFLAGS_LOCAL = $(ALL_CFLAGS)
EXE = regexscan

$(EXE): regexscan.c $(FOLIB) $(VARS)
	$(CC) $< $(CFLAGS_LOCAL) -o $@

all: $(EXE)

#######################
# library build rules #
#######################
$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)
	@mkdir -p $(CONFDIR)/mods-enabled
	@if test ! -e $(CONFDIR)/mods-enabled/$(EXE); then \
		ln -s $(MODDIR)/$(EXE) $(CONFDIR)/mods-enabled; \
	fi

uninstall:
	@rm -rf $(DESTDIR)$(MODDIR)/$(EXE)
	@rm -f $(CONFDIR)/mods-enabled/$(EXE)


test: all
	@echo "*** No tests available for src/$(EXE) ***"

coverage:
	@echo "NOTICE: no coverage available for regexscan"

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean test
