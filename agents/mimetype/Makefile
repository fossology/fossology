# FOSSology Makefile - agents/mimetype
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq -lmagic $(ALL_CFLAGS)
EXE=mimetype

all: $(EXE)

$(EXE): %: %.c $(DB) $(REPO) $(VARS)
	$(CC) $< $(CFLAGS_LOCAL) -o $@

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

test: all
	@echo "*** No tests available for agents/$(EXE) ***"

clean:
	rm -f $(EXE) *.o core

include $(DEPS)

.PHONY: all install uninstall clean test
