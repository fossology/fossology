# Filter_license Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
EXE=selftest
INC=-I../../$(BUILDINC) -I../ununpack
LIB=-L../../$(BUILDLIB) -lfossrepo -lfossdb -lpq

all: $(EXE)

selftest: selftest.c
	$(CC) $(CFLAGS) -DBSAMLIC='"$(AGENTDATADIR)/License.bsam"' -DLICDIR='"$(AGENTDATADIR)/licenses"' -DAGENTDIR='"$(AGENTDIR)"' $(INC) -o selftest selftest.c ../ununpack/checksum.c ../ununpack/md5.c ../ununpack/sha1.c $(LIB)

InstallationCreate:
	$(CP) selftest ../../install/$(AGENTDIR)

install: all
	$(CP) selftest $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/selftest

clean:
	$(RM) *.o core $(EXE)

