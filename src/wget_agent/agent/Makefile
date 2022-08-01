# SPDX-FileCopyrightText: © 2008-2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

EXE = wget_agent
CHKHDR = ../../ununpack/agent/checksum.h
CHKSRC = $(CHKHDR:%.h=%.c)
OBJECTS = wget_agent.o
HDIR = $(OBJS:.o=.h)
COVERAGE = $(OBJECTS:%.o=%_cov.o)
LDFLAGS_LOCAL = $(FO_LDFLAGS) $(shell libgcrypt-config --libs)
CFLAGS_LOCAL = $(FO_CFLAGS) $(shell libgcrypt-config --cflags)

all: $(EXE)

$(EXE): main.c $(FOLIB) libwget_agent.a $(CHKHDR) $(CHKSRC) $(DB) $(REPO) $(VARS)
	$(CC) main.c libwget_agent.a $(CHKSRC) $(CFLAGS_LOCAL) $(DEFS) \
      $(LDFLAGS_LOCAL) -o $@

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

$(OBJECTS): %.o: %.c
	$(CC) -c $< $(CFLAGS_LOCAL)

$(COVERAGE): %_cov.o: %.c
	$(CC) -c $< $(CFLAGS_LOCAL) $(FLAG_COV) -o $@

libwget_agent.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libwget_agent_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

clean:
	rm -f $(EXE) *.o core *.a *.gc*

.PHONY: all install uninstall clean

include $(DEPS)
