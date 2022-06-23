# FOSSology Makefile - agents/mimetype
# SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

LDFLAGS_LOCAL = -lmagic $(FO_LDFLAGS)
EXE = mimetype
OBJECTS = finder.o
HDIR = $(OBJS:.o=.h)

COVERAGE = $(OBJECTS:%.o=%_cov.o)

all: $(EXE)

$(EXE): mimetype.c $(FOLIB) libmimetype.a $(DB) $(REPO) $(VARS)
	$(CC) mimetype.c libmimetype.a $(FO_CFLAGS) $(LDFLAGS_LOCAL) $(DEFS) -o $@

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

$(OBJECTS): %.o: %.c
	$(CC) -c $< $(FO_CFLAGS)

$(COVERAGE): %_cov.o: %.c
	$(CC) -c $< $(FO_CFLAGS) $(FLAG_COV) $(DEFS) -o $@

libmimetype.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libmimetype_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

clean:
	rm -f $(EXE) *.o core *.a *.gc*

.PHONY: all install uninstall clean

include $(DEPS)
