# FOSSology Makefile - agent/example_wc_agent
# Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
TOP=../../../
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) $(ALL_CFLAGS)
EXE=wc_agent
CONFDIR = $(DESTDIR)$(SYSCONFDIR)/fossology
MODULEDIR = $(shell dirname `pwd`)

all: $(EXE)

$(EXE): %: %.c $(FOLIB) $(DB) $(REPO) $(VARS)
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
	@if test ! -e $(CONFDIR)/mods-enabled/wc_agent; then \
	ln -s $(MODULEDIR) $(CONFDIR)/mods-enabled/wc_agent ; fi;


uninstall:
	@echo "make $(EXE) uninstall"
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE) \
	rm -f $(CONFDIR)/mods-enabled/wc_agent

test: all
	@echo "*** No tests available for agent/$(EXE) ***"

clean:
	rm -f $(EXE) *.o core

include $(DEPS)

.PHONY: all install uninstall clean test
