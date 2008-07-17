# sqlagent Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
EXE=adj2nest
INC=-I../../$(BUILDINC)
LIB=-L../../$(BUILDLIB) -lfossdb -lpq

all: $(EXE)

adj2nest: adj2nest.c
	$(CC) $(CFLAGS) adj2nest.c -o adj2nest $(INC) $(LIB)

InstallationCreate:
	$(CP) adj2nest ../../install/$(AGENTDIR)

install: all
	$(CP) adj2nest $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/adj2nest

clean:
	$(RM) *.o core $(EXE)

