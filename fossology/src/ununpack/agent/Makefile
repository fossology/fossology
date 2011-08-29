# FOSSology Makefile - agents/ununpack
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
CONFDIR = $(DESTDIR)$(SYSCONFDIR)/fossology
MODULEDIR = $(shell dirname `pwd`)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq -lmagic $(ALL_CFLAGS)
EXE=departition ununpack

CHKHDR= checksum.h md5.h sha1.h
CHKSRC=$(CHKHDR:%.h=%.c) traverse.c utils.c
UUHDR= ununpack.h ununpack-iso.h ununpack-disk.h ununpack-ar.h $(CHKHDR)
UUSRC=$(UUHDR:%.h=%.c)

TESTDIR=tests
OBJECTS= checksum.o md5.o traverse.o ununpack-iso.o sha1.o ununpack-ar.o ununpack-disk.o utils.o
COVERAGE=$(OBJECTS:%.o=%_cov.o)

all: $(FOLIB) $(EXE) 

departition: %: %.c $(VARS)
	$(CC) $< $(ALL_CFLAGS) -o $@

ununpack: ununpack.c libununpack.a $(VARS) $(DB) $(REPO) $(AGENTLIB)
	$(CC) ununpack.c libununpack.a $(CFLAGS_LOCAL) -o $@

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all $(VARS)
	if [ -e $(DESTDIR)$(BINDIR)/departition ] ; then \
	   rm -f $(DESTDIR)$(BINDIR)/departition; \
	fi
	$(INSTALL_PROGRAM) departition $(DESTDIR)$(BINDIR)/departition
	if [ -e $(DESTDIR)$(AGENTDIR)/ununpack ] ; then \
	   rm -f $(DESTDIR)$(AGENTDIR)/ununpack; \
	fi
	$(INSTALL_PROGRAM) ununpack $(DESTDIR)$(AGENTDIR)/ununpack
	@if test ! -e $(CONFDIR)/mods-enabled/ununpack; then \
	  ln -s $(MODULEDIR) $(CONFDIR)/mods-enabled ; fi;



uninstall: $(VARS)
	rm -f $(DESTDIR)$(BINDIR)/departition
	rm -f $(DESTDIR)$(AGENTDIR)/ununpack
	rm -f $(CONFDIR)/mods-enabled/ununpack


$(OBJECTS): %.o: %.c 
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

.PHONY: all install uninstall clean test $(FOLIB)
