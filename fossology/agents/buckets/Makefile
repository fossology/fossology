# FOSSology Makefile - agents/buckets
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq $(ALL_CFLAGS)
CFLAGS_LOCALO=$(CFLAGS_DBO) $(CFLAGS_REPOO) $(CFLAGS_AGENTO) $(ALL_CFLAGS)

EXE=buckets
OBJS=validate.o
HDRS=buckets.h

all: $(EXE)

$(EXE): %: %.c $(DB) $(REPO) $(VARS) $(OBJS) $(HDRS)
	$(CC) $(OBJS) $< $(CFLAGS_LOCAL) -o $@

validate.o: $(DB) $(REPO) $(VARS) $(HDRS) validate.c
	$(CC) -c $(CFLAGS_LOCALO) validate.c

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
