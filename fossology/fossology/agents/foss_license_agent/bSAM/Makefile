# foss_license_agent Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
#CFLAGS=-Wall -g -fstack-check $(CFLAGS2)
#CFLAGS=-Wall -pg $(CFLAGS2)
EXE=bsam-engine SeeSam bsamlist
INC=-I../../../ununpack -I../../../$(BUILDINC)
LIB=-lpq -L../../../$(BUILDLIB) -lfossdb -lfossrepo

all: $(EXE)

bsam-engine: bsam-engine.c
	$(CC) $(CFLAGS) bsam-engine.c -o bsam-engine $(INC) $(LIB)

SeeSam: SeeSam.c
	$(CC) $(CFLAGS) SeeSam.c -o SeeSam

InstallationCreate:
	$(CP) bsam-engine ../../../install/$(AGENTDIR)

install: all
	$(CP) bsam-engine $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/bsam-engine

clean:
	$(RM) *.o core $(EXE)

