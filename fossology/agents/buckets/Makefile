# FOSSology Makefile - agents/buckets
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq $(ALL_CFLAGS)
CFLAGS_LOCALO=$(CFLAGS_DBO) $(CFLAGS_REPOO) $(CFLAGS_AGENTO) $(ALL_CFLAGS)

EXE=buckets
OBJS=validate.o inits.o liccache.o dbcommon.o
HDRS=buckets.h
DEF=-DDATADIR='"$(DATADIR)"'

all: $(EXE)

$(EXE): %: %.c $(DB) $(REPO) $(VARS) $(OBJS) $(HDRS)
	$(CC) $(OBJS) $< $(DEF) $(CFLAGS_LOCAL) -o $@

validate.o: $(DB) $(REPO) $(VARS) $(HDRS) validate.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) validate.c

inits.o: $(DB) $(REPO) $(VARS) $(HDRS) inits.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) inits.c

liccache.o: $(DB) $(REPO) $(VARS) $(HDRS) liccache.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) liccache.c

dbcommon.o: $(DB) $(REPO) $(VARS) $(HDRS) dbcommon.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) dbcommon.c

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
