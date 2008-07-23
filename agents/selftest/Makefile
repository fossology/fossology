# FOSSology Makefile - agents/selftest
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) -lpq $(ALL_CFLAGS)
STDEF=-DBSAMLIC='"$(AGENTDATADIR)/License.bsam"' -DLICDIR='"$(AGENTDATADIR)/licenses"' -DAGENTDIR='"$(AGENTDIR)"'
CKSRC=../ununpack/checksum.c ../ununpack/md5.c ../ununpack/sha1.c
EXE=selftest

all: $(EXE)

$(EXE): %: %.c $(CKSRC) $(CKSRC:%.c=%.h) $(DB) $(REPO) $(VARS)
	$(CC) $< $(CKSRC)  $(STDEF) -I../ununpack $(CFLAGS_LOCAL) -o $@

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
