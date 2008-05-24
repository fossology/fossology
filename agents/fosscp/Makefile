# FOSSology Makefile - agent/fosscp
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

EXE=fosscp_agent

all: $(EXE)

$(EXE): fosscp.php
	chmod ug+x fosscp.php
	cp fosscp.php $(EXE)

install: all $(VARS)
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

test: all
	@echo "*** No tests available for agent/$(EXE) ***"

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean test
