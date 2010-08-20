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
$(EXE): main.c libpkgagent.so $(DB) $(REPO) $(AGENTLIB) $(VARS) $(HDRS)
	$(CC) main.c $(LIBDIR)/libpkgagent.so $(CFLAGS_LOCAL) -o $@

libpkgagent.so: pkgagent.c $(DB) $(REPO) $(AGENTLIB) $(VARS) $(HDRS)
	if expr `rpm --version|awk '{print $$3}'|awk -F. '{print $$1"."$$2}'` \>= 4.5 >/dev/null ; then \
	$(CC) -shared -fPIC $< $(CFLAGS_LOCAL_RPM) -o $@; \
	else \
	$(CC) -shared -fPIC $< $(CFLAGS_LOCAL_RPM_4_4) -o $@; \
	fi

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)	
	$(INSTALL_PROGRAM) libpkgagent.so $(DESTDIR)$(LIBDIR)/libpkgagent.so

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)
	rm -f $(DESTDIR)$(LIBDIR)/libpkgagent.so

test: all 
	$(MAKE) -C $(TESTDIRS) test

coverage: test
	$(MAKE) -C $(TESTDIRS) coverage
	
clean:
	rm -f $(EXE) *.o *.so core; \
	$(MAKE) -C $(TESTDIRS) clean

include $(DEPS)

.PHONY: all install uninstall clean test
