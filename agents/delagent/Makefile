# FOSSology Makefile - agent/delagent
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

TOP=../..

VARS=$(TOP)/Makefile.conf
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) -lpq $(ALL_CFLAGS)
EXE=delagent

all: $(EXE)

$(EXE): %: %.c $(DB) $(REPO) $(VARS)
	$(CC) $< $(CFLAGS_LOCAL) -o $@

# targets to make sure the libfoss libraries we use are up to date
$(DB):
	$(MAKE) -C $(DBPATH) libfossdb.a

$(REPO):
	$(MAKE) -C $(REPOPATH) libfossrepo.a

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

test: all
	@echo "*** No tests available for agent/$(EXE) ***"

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean test
