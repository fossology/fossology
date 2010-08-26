######################################################################
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
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
TOP = ../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL = $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) $(ALL_CFLAGS) -I. -lpq -lpcre
EXE = copyright

SOURCE = cvector.c \
		 radixtree.c \
		 copyright.c \
		 pair.c \

HEADER = cvector.h \
		 radixtree.h \
		 copyright.h \
		 pair.h \
     sql_statements.h

OBJS   = cvector.o \
		 radixtree.o \
		 pair.o \

DOCS   = copyright.dic \
		 names.dic \
		 
all: $(EXE)

$(EXE): copyright.o main.c
	$(CC) $(OBJS) copyright.o main.c $(CFLAGS_LOCAL) -o $@

copyright.o: $(OBJS) $(HEADER) $(DB) $(REPO) $(VARS) copyright.c
	$(CC) $(DIRS) -c $(CFLAGS_LOCAL) copyright.c

$(OBJS): %.o: %.c $(HEADER) $(DB) $(REPO) $(VARS)
	$(CC) -c $(CFLAGS_LOCAL) $<

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)
	$(INSTALL_PROGRAM) $(DOCS) $(DESTDIR)$(AGENTDIR)/$(EXE)

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

clean:
	rm -f *.o $(EXE)
	rm -f False_Positives False_Negatives Matches Found
	$(MAKE) -C test/ clean

.PHONY: all install uninstall clean test
	
