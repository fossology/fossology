# FOSSology Makefile - agents/foss_license_agent/Filter_License
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq $(ALL_CFLAGS:-g=) -fno-stack-protector
EXE=Filter_License filter_clean

FLSRC=Filter_License.c 1sl.c wordcheck.c tokholder.c ../../ununpack/checksum.c ../../ununpack/md5.c ../../ununpack/sha1.c wordregex.c

all: $(EXE)

Filter_License: $(FLSRC) $(FLSRC:%.c=%.h) $(DB) $(REPO) $(VARS)
	$(CC) $(FLSRC) -I../../ununpack $(CFLAGS_LOCAL) -o $@

filter_clean: %: %.c $(DB) $(REPO)
	$(CC) $< $(CFLAGS_LOCAL) -o $@

install: all
	$(INSTALL_PROGRAM) Filter_License $(DESTDIR)$(AGENTDIR)/Filter_License
	$(INSTALL_PROGRAM) filter_clean $(DESTDIR)$(AGENTDIR)/filter_clean

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/Filter_License
	rm -f $(DESTDIR)$(AGENTDIR)/filter_clean

test: all
	@echo "*** No tests available for agents/foss_license_agent/Filter_License ***"

clean:
	rm -f $(EXE) *.o core

include $(DEPS)

.PHONY: all install uninstall clean test
