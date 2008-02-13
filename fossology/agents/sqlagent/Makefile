# sqlagent Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
EXE=sqlagent
INC=-I../../$(BUILDINC)
LIB=-L../../$(BUILDLIB) -lfossdb -lpq

all: $(EXE)

sqlagent: sqlagent.c
	$(CC) $(CFLAGS) sqlagent.c -o sqlagent $(INC) $(LIB)

InstallationCreate:
	$(CP) sqlagent ../../install/$(AGENTDIR)

install: all
	$(CP) sqlagent $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/sqlagent

clean:
	$(RM) *.o core $(EXE)

