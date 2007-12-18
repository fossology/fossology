# mimetype Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
EXE=mimetype
INC=-I../../$(BUILDINC)
LIB=-L../../$(BUILDLIB) -lfossrepo -lfossdb -lpq -lmagic

all: $(EXE)

mimetype: mimetype.c
	$(CC) $(CFLAGS) mimetype.c -o mimetype $(INC) $(LIB)

InstallationCreate:
	$(CP) mimetype ../../install/$(AGENTDIR)

install: all
	$(CP) mimetype $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/mimetype

clean:
	$(RM) *.o core $(EXE)

