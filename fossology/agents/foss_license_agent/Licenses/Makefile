# Licenses Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../../Makefile.conf

EXE=License.bsam

all:

License.bsam:
	chmod u+x ./MkCache
	./MkCache

Test1:	License.bsam
	@echo "Testing to find the best match"
	chmod u+x ./SelfTest
	./SelfTest

Test2:	License.bsam
	@echo "Testing to find all matches"
	chmod u+x ./SelfTest2
	./SelfTest2

InstallationCreate:
	find Raw -type f | grep -v svn | sed -e 's@^Raw/@@' | \
	while read i ; do \
	  $(MKDIR) -p ../../../install/$(AGENTDATADIR)/licenses/"$${i%/*}"; \
	  $(CP) Raw/"$$i" ../../../install/$(AGENTDATADIR)/licenses/"$$i"; \
	done

install: $(EXE)
	$(CP) License.bsam $(AGENTDATADIR)

uninstall:
	$(RM) $(AGENTDATADIR)/License.bsam

clean:
	$(RM) *.o core $(EXE)

