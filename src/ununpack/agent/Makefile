# FOSSology Makefile - agents/ununpack
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)
CONFDIR = $(DESTDIR)$(SYSCONFDIR)

CFLAGS_LOCAL = $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq -lmagic $(ALL_CFLAGS)
EXE = departition ununpack

CHKHDR = checksum.h md5.h sha1.h
CHKSRC = $(CHKHDR:%.h=%.c) traverse.c utils.c
UUHDR = ununpack.h ununpack-iso.h ununpack-disk.h ununpack-ar.h $(CHKHDR) ununpack_globals.h
UUSRC = $(UUHDR:%.h=%.c)

TESTDIR = tests
OBJECTS = checksum.o md5.o traverse.o ununpack-iso.o sha1.o ununpack-ar.o ununpack-disk.o utils.o
COVERAGE = $(OBJECTS:%.o=%_cov.o)

all: $(FOLIB) $(EXE)

departition: %: %.c $(VARS)
	$(CC) $< $(ALL_CFLAGS) -o $@

ununpack: ununpack.c libununpack.a $(VARS) $(DB) $(REPO) $(AGENTLIB) $(UUHDR)
	$(CC) ununpack.c libununpack.a $(CFLAGS_LOCAL) $(DEFS) -o $@

install: all $(VARS)
	if [ -e $(DESTDIR)$(MODDIR)/ununpack/agent/departition ] ; then \
	   rm -f $(DESTDIR)$(MODDIR)/ununpack/agent/departition; \
	fi
	@mkdir -p $(CONFDIR)/mods-enabled
	$(INSTALL_PROGRAM) departition $(DESTDIR)$(MODDIR)/ununpack/agent/departition
	
	$(INSTALL_PROGRAM) ununpack $(DESTDIR)$(MODDIR)/ununpack/agent/ununpack
	@if test ! -e $(CONFDIR)/mods-enabled/ununpack; then \
	  ln -s $(MODDIR)/ununpack $(CONFDIR)/mods-enabled ; fi;

# create wrapper for departition in BINDIR
	@mkdir -p $(DESTDIR)$(BINDIR)
	@echo "#!/bin/bash" > $(DESTDIR)$(BINDIR)/departition
	@echo "MODDIR=$(MODDIR)" >> $(DESTDIR)$(BINDIR)/departition
	@echo "\$$MODDIR/ununpack/agent/departition \$$@" >> $(DESTDIR)$(BINDIR)/departition
	chmod a+x $(DESTDIR)$(BINDIR)/departition

uninstall: $(VARS)
	rm -f $(DESTDIR)$(BINDIR)/departition
	rm -rf $(DESTDIR)$(MODDIR)/ununpack

$(OBJECTS): %.o: %.c $(UUHDR)
	$(CC) -c $< $(CFLAGS_LOCAL)


$(COVERAGE): %_cov.o: %.c
	$(CC) -c $< $(CFLAGS_LOCAL) $(FLAG_COV) -o $@

libununpack.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libununpack_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)


test: libununpack.a
	$(MAKE) -C $(TESTDIR) test

coverage: libununpack_cov.a
	$(MAKE) -C $(TESTDIR) coverage

clean:
	rm -f $(EXE) *.o core *.a *.gc*; \

include $(DEPS)

.PHONY: all install uninstall clean test
