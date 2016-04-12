######################################################################
# Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
# Copyright (C) 2016 Siemens AG
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

CFLAGS_LOCAL = $(FO_CFLAGS) -Werror
CFLAGS_LOCALO = $(FO_CFLAGS)

EXE=regexscan

all: $(EXE)

regexscan: regexscan.o 
	$(CC) regexscan.o $(CFLAGS_LOCAL) $(FO_LDFLAGS) -o $@

regexscan.o: regexscan.c $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) -c $< $(CFLAGS_LOCALO) $(DEFS)

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	@echo "make regexscan agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	@echo "make regexscan agent uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean
