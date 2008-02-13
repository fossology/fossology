# specagent Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
EXE=specagent
INC=-I../../$(BUILDINC)
LIB=-L../../$(BUILDLIB) -lfossrepo -lfossdb -lpq -lextractor

all: $(EXE)

specagent: specagent.c
	$(CC) $(CFLAGS) specagent.c -o specagent $(INC) $(LIB)

InstallationCreate:
	$(CP) specagent ../../install/$(AGENTDIR)

install: all
	$(CP) specagent $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/specagent

clean:
	$(RM) *.o core $(EXE)

