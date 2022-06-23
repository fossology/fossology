# SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

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
