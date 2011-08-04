# FOSSology Makefile - agents/pkgagent
# Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.
LANG=C
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
TESTDIRS=../agent_tests
CFLAGS_LOCAL=$(ALL_CFLAGS) -I/usr/include/rpm -lpq -lrpm -lrpmio
CONFDIR = $(DESTDIR)$(SYSCONFDIR)/fossology
MODULEDIR = $(shell dirname `pwd`)

EXE=pkgagent
HDRS=pkgagent.h
CFLAGS_LOCAL_RPM_4_4=$(CFLAGS_LOCAL) -D_RPM_4_4
CFLAGS_LOCAL_RPM=$(CFLAGS_LOCAL) -D_RPM_4_4_COMPAT

all: $(EXE)
	@echo "make pkgagent agent and test"

$(EXE): main.c $(FOLIB) pkgagent.o $(VARS) $(HDRS)
	@echo "make pkgagent agent"
	$(CC) main.c pkgagent.o  $(CFLAGS_LOCAL) -o $@

pkgagent.o: pkgagent.c pkgagent.h
	if expr `rpm --version|awk '{print $$3}'|awk -F. '{print $$1"."$$2}'` \>= 4.5 >/dev/null ; then \
		$(CC) -c $< $(CFLAGS_LOCAL_RPM); \
	else \
		$(CC) -c $< $(CFLAGS_LOCAL_RPM_4_4); \
	fi

#######################
# library build rules #
#######################
$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	@echo "make pkgagent agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)
	@if test ! -e $(CONFDIR)/mods-enabled/pkgagent; then \
	  ln -s $(MODULEDIR) $(CONFDIR)/mods-enabled/pkgagent ; fi;


uninstall:
	@echo "make pkgagent agent uninstall"
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)
	rm -f $(CONFDIR)/mods-enabled/pkgagent

test: all 
	$(MAKE) -C $(TESTDIRS) test

coverage: pkgagent_cov.o
	$(MAKE) -C $(TESTDIRS) coverage

pkgagent_cov.o: pkgagent.c pkgagent.h
	if expr `rpm --version|awk '{print $$3}'|awk -F. '{print $$1"."$$2}'` \>= 4.5 >/dev/null ; then \
		$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL_RPM) -o $@; \
	else \
		$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL_RPM_4_4) -o $@; \
	fi

clean:
	@echo "make pkgagent agent clean"
	rm -f $(EXE) *.o core *.gc*; \
	$(MAKE) -C $(TESTDIRS) clean

include $(DEPS)

.PHONY: all install uninstall clean test
