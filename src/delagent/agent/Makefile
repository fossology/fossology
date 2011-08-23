# FOSSology Makefile - agent/delagent
# Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.

TOP=../../..

VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
TESTDIRS=../agent_tests

CFLAGS_LOCAL=$(ALL_CFLAGS) -lpq
CONFDIR = $(DESTDIR)$(SYSCONFDIR)/fossology
MODULEDIR = $(shell dirname `pwd`)

EXE=delagent
HDRS=delagent.h

all: $(EXE)
	@echo "make delagent agent and test"

$(EXE): %: %.c $(FOLIB) delagent.o $(VARS) $(HDRS)
	@echo "make delagent agent"
	$(CC) $< delagent.o $(CFLAGS_LOCAL) -o $@

delagent.o: util.c $(HDRS)
	$(CC) -C $< $(CFLAGS_LOCAL) -o $@

#######################
# library build rules #
#######################
$(FOLIB):
	$(MAKE) -c $(FOLIBDIR)

install: all
	@echo "make delagent agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)
	@if test ! -e $(CONFDIR)/mods-enabled/delagent; then \
          ln -s $(MODULEDIR) $(CONFDIR)/mods-enabled/delagent ; fi;

uninstall:
	@echo "make delagent agent uninstall"
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)
	rm -f $(CONFDIR)/mods-enabled/delagent

test: all
	$(MAKE) -C $(TESTDIRS) test

coverage: delagent_cov.o
	$(MAKE) -C $(TESTDIRS) coverage

delagent_cov.o: util.c $(HDRS)
	$(CC) -C $< $(FLAG_COV) $(CFLAGS_LOCAL) -o $@

clean:
	@echo "make delagent agent clean"
	rm -f $(EXE) *.o core *.gc*
	$(MAKE) -C $(TESTDIRS) clean

include $(DEPS)

.PHONY: all install uninstall clean test coverage
