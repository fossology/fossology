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
TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

DEF=-DPROJECTSTATEDIR='"$(PROJECTSTATEDIR)"' -DDATADIR='"$(DATADIR)"'
LOCAL_CFLAGS= $(DEF) $(ALL_CFLAGS) $(DEFS)

EXE=maintagent
OBJS=usage.o utils.o process.o
HDRS=maintagent.h

all: $(EXE)
	@echo "make maintagent agent"

$(EXE): maintagent.c $(FOLIB) $(OBJS) $(HDRS)
	$(CC) maintagent.c $(OBJS) -lpq $(LOCAL_CFLAGS) $(FO_LDFLAGS) -o $@

usage.o: $(HDRS) usage.c
	$(CC) -c $(LOCAL_CFLAGS) usage.c

utils.o: $(HDRS) utils.c
	$(CC) -c $(LOCAL_CFLAGS) utils.c

process.o: $(HDRS) process.c
	$(CC) -c $(LOCAL_CFLAGS) process.c

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	@echo "make maintagent  agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	@echo "make maintagent  agent uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean test $(FOLIB) coverage
