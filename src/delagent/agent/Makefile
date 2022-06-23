#  SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.

#  SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL = $(FO_CFLAGS) $(shell libgcrypt-config --cflags)
LDFLAGS_LOCAL = $(FO_LDFLAGS) -lpq $(shell libgcrypt-config --libs)
CONFDIR = $(DESTDIR)$(SYSCONFDIR)

EXE = delagent
HDRS = delagent.h

all: $(EXE)

$(EXE): %: %.c $(FOLIB) delagent.o $(VARS) $(HDRS)
	$(CC) $< delagent.o $(LDFLAGS_LOCAL) $(CFLAGS_LOCAL) $(DEFS) -o $@

delagent.o: util.c $(HDRS)
	$(CC) -c $< $(CFLAGS_LOCAL) -o $@

delagent_cov.o: util.c $(HDRS)
	$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL) $(DEFS) -o $@

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o core *.gc*

.PHONY: all install uninstall clean

include $(DEPS)
