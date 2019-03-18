######################################################################
# Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.
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

LANG = C
TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL = $(FO_CFLAGS) -I/usr/include/rpm 
LDFLAGS_LOCAL = $(FO_LDFLAGS) -lpq -lrpm -lrpmio

EXE = pkgagent
HDRS = pkgagent.h

all: $(EXE)

$(EXE): main.c $(FOLIB) pkgagent.o $(VARS) $(HDRS)
	$(CC) main.c pkgagent.o $(LDFLAGS_LOCAL) $(CFLAGS_LOCAL) $(DEFS) -o $@

pkgagent.o: pkgagent.c pkgagent.h
	$(CC) -c $< $(CFLAGS_LOCAL)

pkgagent_cov.o: pkgagent.c pkgagent.h
	$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL) -o $@

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o core *.gc*

.PHONY: all install uninstall clean

include $(DEPS)
