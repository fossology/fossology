# SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DEF = -DPROJECTSTATEDIR='"$(PROJECTSTATEDIR)"' -DDATADIR='"$(DATADIR)"'
CFLAGS_LOCAL = $(DEF) $(FO_CFLAGS) $(DEFS)

EXE = maintagent
OBJS = usage.o utils.o process.o
HDRS = maintagent.h

all: $(EXE)

$(EXE): maintagent.c $(FOLIB) $(OBJS) $(HDRS)
	$(CC) maintagent.c $(OBJS) $(FO_LDFLAGS) $(CFLAGS_LOCAL) -o $@

usage.o: $(HDRS) usage.c
	$(CC) -c $(CFLAGS_LOCAL) usage.c

utils.o: $(HDRS) utils.c
	$(CC) -c $(CFLAGS_LOCAL) utils.c

process.o: $(HDRS) process.c
	$(CC) -c $(CFLAGS_LOCAL) process.c

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean $(FOLIB)
