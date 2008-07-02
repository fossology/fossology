# foss_license_agent Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
#CFLAGS=-Wall -g -fstack-check $(CFLAGS2)
#CFLAGS=-Wall -pg $(CFLAGS2)
EXE=licinspect
INC=-I../../../$(BUILDINC)
LIB=-lpq -L../../../$(BUILDLIB) -lfossdb -lfossrepo

all: $(EXE)

licinspect: licinspect.c
	$(CC) $(CFLAGS) licinspect.c -o licinspect $(INC) $(LIB)

InstallationCreate:
	$(CP) licinspect ../../../install/$(AGENTDIR)

install: all
	$(CP) licinspect $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/licinspect

clean:
	$(RM) *.o core $(EXE)

