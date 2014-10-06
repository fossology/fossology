#####################################################################
# Copyright (C) 2013 Hewlett-Packard Development Company, L.P.
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

DEF = -DPROJECTSTATEDIR='"$(PROJECTSTATEDIR)"' -DDATADIR='"$(DATADIR)"'
CFLAGS_LOCAL = $(DEF) $(ALL_CFLAGS) $(DEFS)

EXE = demomod
OBJS = usage.o utils.o process.o
HDRS = demomod.h

all: $(EXE)
	@echo "make demomod agent"

$(EXE): demomod.c $(FOLIB) $(OBJS) $(HDRS)
	$(CC) demomod.c $(OBJS) -lpq $(CFLAGS_LOCAL) -o $@

usage.o: $(HDRS) usage.c
	$(CC) -c $(CFLAGS_LOCAL) usage.c

utils.o: $(HDRS) utils.c
	$(CC) -c $(CFLAGS_LOCAL) utils.c

process.o: $(HDRS) process.c
	$(CC) -c $(CFLAGS_LOCAL) process.c

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	@echo "make demomod agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	@echo "make demomod agent uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean test $(FOLIB) coverage
