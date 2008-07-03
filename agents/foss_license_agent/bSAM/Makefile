# FOSSology Makefile - agents/foss_licenses_agent
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) -lpq $(ALL_CFLAGS:-g=)
EXE=bsam-engine
UTILS=SeeSam bsamlist

all: $(EXE) $(UTILS)

$(EXE): %: %.c $(DB) $(REPO) $(VARS)
	$(CC) $< $(CFLAGS_LOCAL) -o $@

$(UTILS): %: %.c $(VARS)
	$(CC) $< $(CFLAGS) -o $@

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)
# we don't install the UTILS or the stuff in docs/ currently

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

test: all
	@echo "*** No tests available for agent/$(EXE) ***"

clean:
	rm -f $(EXE) $(UTILS) *.o core

include $(DEPS)

.PHONY: all install uninstall clean test
