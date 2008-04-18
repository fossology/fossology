# scheduler Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../Makefile.conf

CC=gcc -DPROJECTUSER='"$(PROJECTUSER)"' -DPROJECTGROUP='"$(PROJECTGROUP)"'
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g $(CFLAGS2)
#CFLAGS=-Wall -g -efence $(CFLAGS2)
OBJ=scheduler.o spawn.o sockets.o agents.o dbq.o dbstatus.o hosts.o debug.o dberror.o
SRC=scheduler.c spawn.c sockets.c agents.c dbq.c dbstatus.c hosts.c debug.c dberror.c
HDR=scheduler.h spawn.h sockets.h agents.h dbq.h dbstatus.h hosts.h debug.h dberror.h
INC=-I../$(BUILDINC)
LIB=-L../$(BUILDLIB)
LIBDB=-lpq -lfossdb
LIBREP=-lfossrepo

all: scheduler mkconfig

InstallationCreate:
	$(CP) scheduler ../install/$(AGENTDIR)
	$(CP) mkconfig ../install/$(AGENTDIR)
	$(MKDIR) -p ../install/etc/init.d/
	$(MKDIR) -p ../install/etc/default/
	cat init.d | sed -e "s@AGENTDIR@$(AGENTDIR)@" > ../install/etc/init.d/fossology
	$(CP) default ../install/etc/default/fossology
	chmod 755 ../install/etc/init.d/fossology

install: all
	$(CP) scheduler $(AGENTDIR)
	$(CP) mkconfig $(AGENTDIR)
	if [ -f $(DATADIR)/scheduler.conf ] ; then ./mkconfig -o $(DATADIR)/scheduler.conf -L ; fi

uninstall:
	$(RM) $(BINDIR)/scheduler
	$(RM) $(LIBEXECDIR)/mkconfig
	$(RM) $(DATADIR)/scheduler.conf

scheduler: $(OBJ) $(HDR)
	$(CC) $(CFLAGS) $(INC) -o scheduler $(OBJ) $(LIB) $(LIBDB) $(LIBREP)

mkconfig: mkconfig.c
	$(CC) $(CFLAGS) $(INC) -DDATADIR='"$(AGENTDATADIR)"' -DBINDIR='"$(AGENTDIR)"' -DVARDATADIR='"$(VARDATADIR)"' mkconfig.c -o mkconfig $(LIB) $(LIBREP)

%.o: %.c
	$(CC) $(CFLAGS) $(INC) -DDEFAULTSETUP='"$(AGENTDATADIR)/scheduler.conf"' -c $<

clean:
	$(RM) $(OBJ) core scheduler mkconfig

