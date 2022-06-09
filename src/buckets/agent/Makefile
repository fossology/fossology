# SPDX-FileCopyrightText: © 2010-2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only


TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DEF = -DPROJECTSTATEDIR='"$(PROJECTSTATEDIR)"' -DDATADIR='"$(DATADIR)"'
CFLAGS_LOCAL = $(DEF) $(FO_CFLAGS) $(DEFS) -fPIC

EXE = buckets
OBJS = validate.o inits.o liccache.o walk.o leaf.o match.o container.o child.o write.c
HDRS = buckets.h

all: $(EXE)

$(EXE): buckets.c $(FOLIB) $(OBJS) $(HDRS)
	$(CC) buckets.c $(OBJS) $(FO_LDFLAGS) -lpq $(CFLAGS_LOCAL) -o $@

write.o: $(HDRS) write.c
	$(CC) -c $(CFLAGS_LOCAL) write.c

child.o: $(HDRS) child.c
	$(CC) -c $(CFLAGS_LOCAL) child.c

container.o: $(HDRS) container.c
	$(CC) -c $(CFLAGS_LOCAL) container.c

match.o: $(HDRS) match.c
	$(CC) -c $(CFLAGS_LOCAL) match.c

walk.o: $(HDRS) walk.c
	$(CC) -c $(CFLAGS_LOCAL) walk.c

leaf.o: $(HDRS) leaf.c
	$(CC) -c $(CFLAGS_LOCAL) leaf.c

validate.o: $(HDRS) validate.c
	$(CC) -c $(CFLAGS_LOCAL) validate.c

inits.o: $(HDRS) inits.c
	$(CC) -c $(CFLAGS_LOCAL) inits.c

liccache.o: $(HDRS) inits.c
	$(CC) -c $(CFLAGS_LOCAL) liccache.c

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean $(FOLIB)
