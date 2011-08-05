# FOSSology Makefile - agents/mimetype
# Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
TOP=../../../
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq -lmagic $(ALL_CFLAGS)
EXE=mimetype

all: $(EXE)

$(EXE): %: %.c $(DB) $(REPO) $(VARS)
	@echo "make $(EXE)"
	$(CC) $< $(CFLAGS_LOCAL) -o $@

#######################
# library build rules #
#######################
$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	@echo "make $(EXE) install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)

uninstall:
	@echo "make $(EXE) uninstall"
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

test: all
	@echo "make test $(EXE)"
	$(MAKE) -C $(TESTDIR) test

coverage:
	@echo "make coverage $(EXE)"
	$(MAKE) -C $(TESTDIR) coverage

clean:
	rm -f $(EXE) *.o core

include $(DEPS)

.PHONY: all install uninstall clean test
