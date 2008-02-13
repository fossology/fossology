# Licenses Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../../Makefile.conf

EXE=License.bsam

all:

License.bsam:
	./MkCache

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

