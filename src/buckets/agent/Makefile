#####################################################################
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
TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

DEF=-DPROJECTSTATEDIR='"$(PROJECTSTATEDIR)"' -DDATADIR='"$(DATADIR)"'
CFLAGS_LOCAL= $(DEF) $(ALL_CFLAGS) $(DEFS)

EXE=buckets
OBJS=validate.o inits.o liccache.o walk.o leaf.o match.o container.o child.o write.c
HDRS=buckets.h

all: $(EXE)
	@echo "make bucket agent"

$(EXE): buckets.c $(FOLIB) $(OBJS) $(HDRS)
	$(CC) buckets.c $(OBJS) -lpq $(CFLAGS_LOCAL) -o $@

write.o: $(HDRS) write.c
	$(CC) -c $(CFLAGS_LOCAL) write.c

child.o: $(HDRS) child.c
	$(CC) -c $(CFLAGS_LOCAL) child.c

container.o: $(HDRS) container.c
	$(CC) -c $(CFLAGS_LOCAL) container.c

match.o: $(HDRS) match.c
	$(CC) -c $(CFLAGS_LOCAL) match.c

walk.o: $(HDRS) walk.c
	$(CC) -c $(CFLAGS_LOCAL) walk.c

leaf.o: $(HDRS) leaf.c
	$(CC) -c $(CFLAGS_LOCAL) leaf.c

validate.o: $(HDRS) validate.c
	$(CC) -c $(CFLAGS_LOCAL) validate.c

inits.o: $(HDRS) inits.c
	$(CC) -c $(CFLAGS_LOCAL) inits.c

liccache.o: $(HDRS) inits.c
	$(CC) -c $(CFLAGS_LOCAL) liccache.c

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	@echo "make buckets agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	@echo "make buckets agent uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean test $(FOLIB) coverage
