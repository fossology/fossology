# FOSSology Makefile - agents/mimetype
# Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

LOCAL_CFLAGS=$(DB_CFLAGS) $(REPO_CFLAGS) $(AGENT_CFLAGS) -lpq -lmagic $(ALL_CFLAGS)
EXE=mimetype
OBJECTS=finder.o
HDIR=$(OBJS:.o=.h)
TESTDIR=../agent_tests

COVERAGE=$(OBJECTS:%.o=%_cov.o)

all: $(EXE)

$(EXE): mimetype.c $(FOLIB) libmimetype.a $(DB) $(REPO) $(VARS)
	@echo "make $(EXE)"
	$(CC) mimetype.c libmimetype.a $(LOCAL_CFLAGS) $(FO_LDFLAGS) $(DEFS) -o $@

install: all
	@echo "make $(EXE) install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	@echo "make $(EXE) uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

test: all
	@echo "make test $(EXE)"
	$(MAKE) -C $(TESTDIR) test

coverage:
	@echo "make coverage $(EXE)"
	$(MAKE) -C $(TESTDIR) coverage

$(OBJECTS): %.o: %.c
	$(CC) -c $< $(LOCAL_CFLAGS)

$(COVERAGE): %_cov.o: %.c
	$(CC) -c $< $(LOCAL_CFLAGS) $(COV_CFLAGS) $(DEFS) -o $@

libmimetype.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libmimetype_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

clean:
	rm -f $(EXE) *.o core *.a *.gc*

include $(DEPS)

.PHONY: all install uninstall clean test
