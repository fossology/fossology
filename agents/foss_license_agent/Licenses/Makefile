# FOSSology Makefile - agents/foss_license_agent/Licenses
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

BSAM=../bSAM/bsam-engine ../bSAM/SeeSam
FL=../Filter_License/Filter_License
EXE=License.bsam

all:

# no default targets
# this Makefile is really only useful on a system with fossology already
# installed and the database setup. For installing new systems we need
# to do this work post-install. Thus Makefile is only for the convenience
# of developers.

$(EXE): $(FL)
	chmod u+x ./MkCache
	./MkCache

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)

test: all test-best test-all

test-best: License.bsam $(BSAM)
	@echo "Testing to find the best match"
	chmod u+x ./SelfTest
	./SelfTest

test-all: License.bsam $(BSAM)
	@echo "Testing to find all matches"
	chmod u+x ./SelfTest2
	./SelfTest2

# dependency targets
$(BSAM):
	$(MAKE) -C ../bSAM

$(FL):
	$(MAKE) -C ../Filter_License

clean:
	rm -f $(EXE) *.o core

.PHONY: all install uninstall clean test
