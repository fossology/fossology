# FOSSology Makefile - agents/mimetype
# Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL = $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq -lmagic $(ALL_CFLAGS)
EXE = mimetype
OBJECTS = finder.o
HDIR = $(OBJS:.o=.h)

COVERAGE = $(OBJECTS:%.o=%_cov.o)

all: $(EXE)

$(EXE): mimetype.c $(FOLIB) libmimetype.a $(DB) $(REPO) $(VARS)
	$(CC) mimetype.c libmimetype.a $(FO_LDFLAGS) $(CFLAGS_LOCAL) $(DEFS) -o $@

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

$(OBJECTS): %.o: %.c
	$(CC) -c $< $(CFLAGS_LOCAL)

$(COVERAGE): %_cov.o: %.c
	$(CC) -c $< $(CFLAGS_LOCAL) $(FLAG_COV) $(DEFS) -o $@

libmimetype.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libmimetype_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

clean:
	rm -f $(EXE) *.o core *.a *.gc*

.PHONY: all install uninstall clean

include $(DEPS)
