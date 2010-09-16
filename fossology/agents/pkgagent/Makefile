# FOSSology Makefile - agents/pkgagent
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
TESTDIRS=tests
CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -I/usr/include/rpm -lpq -lrpm $(ALL_CFLAGS)

EXE=pkgagent
HDRS=pkgagent.h
CFLAGS_LOCAL_RPM_4_4=$(CFLAGS_LOCAL) -D_RPM_4_4
CFLAGS_LOCAL_RPM=$(CFLAGS_LOCAL) -D_RPM_4_4_COMPAT

all: $(EXE)
$(EXE): main.c pkgagent.o $(DB) $(REPO) $(AGENTLIB) $(VARS) $(HDRS)
	$(CC) main.c pkgagent.o  $(CFLAGS_LOCAL) -o $@

pkgagent.o: pkgagent.c pkgagent.h
	if expr `rpm --version|awk '{print $$3}'|awk -F. '{print $$1"."$$2}'` \>= 4.5 >/dev/null ; then \
	$(CC) -c $< $(CFLAGS_LOCAL_RPM); \
	else \
	$(CC) -c $< $(CFLAGS_LOCAL_RPM_4_4); \
	fi

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)	

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

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
	rm -f $(EXE) *.o *.so core *.gc*; \
	$(MAKE) -C $(TESTDIRS) clean

include $(DEPS)

.PHONY: all install uninstall clean test
