# wget_agent Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc -DPROXYFILE='"$(AGENTDATADIR)/proxy.conf"'
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
EXE=wget_agent
INC=-I../../$(BUILDINC)
LIB=-L../../$(BUILDLIB) -lfossrepo -lfossdb -lpq

WGETSRC=wget_agent.c ../ununpack/checksum.c ../ununpack/md5.c ../ununpack/sha1.c

all: $(EXE)

wget_agent: $(WGETSRC)
	$(CC) $(CFLAGS) $(WGETSRC) -o wget_agent $(INC) $(LIB)

InstallationCreate:
	cp wget_agent ../../install/$(AGENTDIR)/wget_agent
	chmod a+rx ../../install/$(AGENTDIR)/wget_agent

install: all
	$(CP) wget_agent $(AGENTDIR)
	cp wget_agent $(AGENTDIR)/wget_agent
	chmod a+rx $(AGENTDIR)/wget_agent

uninstall:
	$(RM) $(AGENTDIR)/wget_agent

clean:
	$(RM) $(EXE)

