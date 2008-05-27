# Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
EXE=fc
INC=-I../../$(BUILDINC)
LIB=-L../../$(BUILDLIB) -lfossrepo -lfossdb -lpq

all: $(EXE)

fc: fc.c
	$(CC) $(CFLAGS) fc.c -o fc $(INC) $(LIB)

InstallationCreate:
	$(CP) fc ../../install/$(AGENTDIR)

install: all
	$(CP) fc $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/fc

clean:
	$(RM) *.o core $(EXE)

