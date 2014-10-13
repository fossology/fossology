# FOSSology Makefile - agent/example_wc_agent
# Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

TOP = ../../../
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL = $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) $(ALL_CFLAGS)
EXE = wc_agent
MOD_NAME = example_wc_agent
MOD_SUBDIR = agent

all: $(EXE)

$(EXE): %: %.c $(FOLIB) $(DB) $(REPO) $(VARS)
	$(CC) $< $(CFLAGS_LOCAL) -o $@

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/$(EXE)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean

include $(DEPS)
