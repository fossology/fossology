# SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.
# SPDX-FileCopyrightText: © 2016 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only

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
