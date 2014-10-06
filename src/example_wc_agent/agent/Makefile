# FOSSology Makefile - agent/example_wc_agent
# Copyright (C) 2008-2011 Hewlett-Packard Development Company, L.P.
TOP = ../../../
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL = $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) $(ALL_CFLAGS)
EXE = wc_agent
MOD_NAME = example_wc_agent
MOD_SUBDIR = agent

all: $(EXE)
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR)"

$(EXE): %: %.c $(FOLIB) $(DB) $(REPO) $(VARS)
	@echo "make $(EXE)"
	$(CC) $< $(CFLAGS_LOCAL) -o $@

#######################
# library build rules #
#######################
$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

install: all
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)/$(EXE)

uninstall:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

test: all
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) test"

coverage: test
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) clean"

clean:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR) clean"
	rm -f $(EXE) *.o core

include $(DEPS)

.PHONY: all install uninstall clean test coverage
