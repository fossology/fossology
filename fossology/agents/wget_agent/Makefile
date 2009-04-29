# FOSSology Makefile - agents/wget_agent
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq $(ALL_CFLAGS)
PROXYDEF=-DPROXYFILE='"$(SYSCONFDIR)/fossology/Proxy.conf"'
EXE=wget_agent
CHKHDR=../ununpack/checksum.h ../ununpack/md5.h ../ununpack/sha1.h
CHKSRC=$(CHKHDR:%.h=%.c)

all: $(EXE)

$(EXE): %: %.c $(CHKHDR) $(CHKSRC) $(DB) $(REPO) $(VARS)
	$(CC) $< $(CHKSRC) $(PROXYDEF) $(CFLAGS_LOCAL) -o $@

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

test: all
	@echo "*** No tests available for agents/$(EXE) ***"

clean:
	rm -f $(EXE) *.o core

include $(DEPS)

.PHONY: all install uninstall clean test
