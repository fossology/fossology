# Makefile for example programs
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
EXE=wc_agent
INC=-I../../$(BUILDINC)
LIB=-L../../$(BUILDLIB) -lfossrepo -lfossdb -lpq

all:	$(EXE)

wc_agent:	wc_agent.c
	$(CC) $(CFLAGS) wc_agent.c -o wc_agent $(INC) $(LIB)

InstallationCreate:
	# Uncomment to install
	# $(CP) wc_agent ../../install/$(AGENTDIR)

install:
	# Uncomment to install
	# $(CP) wc_agent $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/wc_agent

clean:
	$(RM) *.o core $(EXE)

