# FOSSology Makefile - agents/buckets
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq $(ALL_CFLAGS)
CFLAGS_LOCALO=$(CFLAGS_DBO) $(CFLAGS_REPOO) $(CFLAGS_AGENTO) $(ALL_CFLAGS)

EXE=buckets
OBJS=validate.o inits.o liccache.o walk.o leaf.o match.o container.o child.o write.c
HDRS=buckets.h
DEF=-DPROJECTSTATEDIR='"$(PROJECTSTATEDIR)"' -DDATADIR='"$(DATADIR)"'

all: $(EXE)

$(EXE): %: %.c $(DB) $(REPO) $(VARS) $(OBJS) $(HDRS)
	$(CC) $(OBJS) $< $(DEF) $(CFLAGS_LOCAL) -o $@

write.o: $(DB) $(REPO) $(VARS) $(HDRS) write.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) write.c

child.o: $(DB) $(REPO) $(VARS) $(HDRS) child.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) child.c

container.o: $(DB) $(REPO) $(VARS) $(HDRS) container.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) container.c

match.o: $(DB) $(REPO) $(VARS) $(HDRS) match.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) match.c

walk.o: $(DB) $(REPO) $(VARS) $(HDRS) walk.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) walk.c

leaf.o: $(DB) $(REPO) $(VARS) $(HDRS) leaf.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) leaf.c

validate.o: $(DB) $(REPO) $(VARS) $(HDRS) validate.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) validate.c

inits.o: $(DB) $(REPO) $(VARS) $(HDRS) inits.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) inits.c

liccache.o: $(DB) $(REPO) $(VARS) $(HDRS) liccache.c
	$(CC) $(DEF) -c $(CFLAGS_LOCALO) liccache.c

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

test: all
	@echo "*** No tests available for agents/$(EXE) ***"

coverage:
	@echo "NOTICE: no coverage available for buckets"

clean:
	rm -f $(EXE) *.o core

include $(DEPS)

.PHONY: all install uninstall clean test
