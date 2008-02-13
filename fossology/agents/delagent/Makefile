# Filter_license Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
EXE=delagent
INC=-I../../$(BUILDINC)
LIB=-L../../$(BUILDLIB) -lfossrepo -lfossdb -lpq

all: $(EXE)

delagent: delagent.c
	$(CC) $(CFLAGS) $(INC) -o delagent delagent.c $(LIB)

InstallationCreate:
	$(CP) delagent ../../install/$(AGENTDIR)

install: all
	$(CP) delagent $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/delagent

clean:
	$(RM) *.o core $(EXE)

