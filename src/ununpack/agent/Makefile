# FOSSology Makefile - agents/ununpack
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq -lmagic $(ALL_CFLAGS)
EXE=departition checksum ununpack

CHKHDR= checksum.h md5.h sha1.h
CHKSRC=$(CHKHDR:%.h=%.c)
UUHDR= ununpack.h ununpack-iso.h ununpack-disk.h ununpack-ar.h metahandle.h $(CHKHDR)
UUSRC=$(UUHDR:%.h=%.c)

TESTDIR=tests
OBJECTS=$(UUHDR:%.h=%.o)
COVERAGE=$(OBJECTS:%.o=%_cov.o)

all: $(EXE)

checksum: $(CHKHDR) $(CHKSRC) $(VARS)
	$(CC) $(CHKSRC) -DMAIN $(ALL_CFLAGS) -o $@

departition: %: %.c $(VARS)
	$(CC) $< $(ALL_CFLAGS) -o $@

ununpack: main.c libununpack.a $(VARS) $(DB) $(REPO) $(AGENTLIB)
	$(CC) main.c libununpack.a $(CFLAGS_LOCAL) -o $@

metahandle.h: metahandle.tab metahandle.pl
	if [ ! -x metahandle.pl ] ; then chmod u+x metahandle.pl; fi
	./metahandle.pl

metahandle.c: metahandle.tab metahandle.pl
	if [ ! -x metahandle.pl ] ; then chmod u+x metahandle.pl; fi
	./metahandle.pl

install: all $(VARS)
	if [ -e $(DESTDIR)$(BINDIR)/departition ] ; then \
	   rm -f $(DESTDIR)$(BINDIR)/departition; \
	fi
	$(INSTALL_PROGRAM) departition $(DESTDIR)$(BINDIR)/departition
	if [ -e $(DESTDIR)$(AGENTDIR)/ununpack ] ; then \
	   rm -f $(DESTDIR)$(AGENTDIR)/ununpack; \
	fi
	$(INSTALL_PROGRAM) ununpack $(DESTDIR)$(AGENTDIR)/ununpack
	$(INSTALL_PROGRAM) checksum $(DESTDIR)$(LIBEXECDIR)/checksum
	$(INSTALL_DATA) ununpack.conf $(SYSCONFDIR)/fossology/agents.d/


uninstall: $(VARS)
	rm -f $(DESTDIR)$(BINDIR)/departition
	rm -f $(DESTDIR)$(LIBEXECDIR)/checksum
	rm -f $(DESTDIR)$(AGENTDIR)/ununpack

$(OBJECTS): %.o: %.c metahandle.h
	$(CC) -c $< $(CFLAGS_LOCAL) 


$(COVERAGE): %_cov.o: %.c metahandle.h
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
	rm -f $(EXE) *.o core metahandle.h metahandle.c *.a *.gc*; \

include $(DEPS)

.PHONY: all install uninstall clean test
