# FOSSology Makefile - agents/foss_license_agent/Licenses
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

BSAM=../bSAM/bsam-engine ../bSAM/SeeSam

all: mkbsamcache

# include the preprocessing stuff
include $(TOP)/Makefile.process
# generate the postinstall script
mkbsamcache: mkbsamcache-process
	chmod +x mkbsamcache

bsam-test1: bsam-test1-process
	chmod +x bsam-test1

bsam-test2: bsam-test2-process
	chmod +x bsam-test2

install: all
	find Raw -type f | grep -v svn | sed -e 's@^Raw/@@' | \
        while read i ; do \
           mkdir -p $(DESTDIR)$(AGENTDATADIR)/licenses/"$${i%/*}"; \
           $(INSTALL_DATA) Raw/"$$i" $(DESTDIR)$(AGENTDATADIR)/licenses/"$$i"; \
        done

	$(INSTALL_PROGRAM) mkbsamcache $(DESTDIR)$(AGENTDIR)/mkbsamcache
	@# these don't work yet
	@#$(INSTALL_PROGRAM) bsam-test1 $(DESTDIR)$(AGENTDIR)/bsam-test1
	@#$(INSTALL_PROGRAM) bsam-test2 $(DESTDIR)$(AGENTDIR)/bsam-test2

uninstall:
	@# we only want to remove the licenses that we installed
	find Raw -type f | grep -v svn | sed -e 's@^Raw/@@' | \
        while read i ; do \
           if [ -f $(DESTDIR)$(AGENTDATADIR)/licenses/"$$i" ]; then \
              rm -f $(DESTDIR)$(AGENTDATADIR)/licenses/"$$i"; \
	   fi; \
	done
	(if [ -d "$(DESTDIR)$(AGENTDATADIR)" ]; then \
	   cd $(DESTDIR)$(AGENTDATADIR); \
	   for dir in `find licenses -type d`; do \
              rmdir --ignore-fail-on-non-empty -p $$dir; \
	   done; \
	fi)

	@# try to remove the dir one time w/o error supression so
	@# we get an error if the user has stuff in there
	if [ -d "$(DESTDIR)$(AGENTDATADIR)/licenses/" ]; then \
	   rmdir $(DESTDIR)$(AGENTDATADIR)/licenses/; \
	fi

	rm -f $(DESTDIR)$(AGENTDIR)/mkbsamcache
	rm -f $(DESTDIR)$(AGENTDIR)/bsam-test1
	rm -f $(DESTDIR)$(AGENTDIR)/bsam-test2

	[ ! -d $(DESTDIR)$(AGENTDATADIR) ] || rmdir $(DESTDIR)$(AGENTDATADIR)
	[ ! -d $(DESTDIR)$(DATADIR) ] || rmdir $(DESTDIR)$(DATADIR)
	[ ! -d $(DESTDIR)$(AGENTDIR) ] || rmdir $(DESTDIR)$(AGENTDIR)
	[ ! -d $(DESTDIR)$(LIBEXECDIR) ] || rmdir $(DESTDIR)$(LIBEXECDIR)

clean:
	rm -f *.o core mkbsamcache bsam-test1 bsam-test2

.PHONY: all install uninstall clean test
