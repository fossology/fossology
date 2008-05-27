# engine-shell Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g $(CFLAGS2)
EXE=engine-shell

all: $(EXE)

engine-shell: engine-shell.c
	$(CC) $(CFLAGS) engine-shell.c -o engine-shell

InstallationCreate:
	$(CP) engine-shell ../../install/$(AGENTDIR)

install: all
	$(CP) engine-shell $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/engine-shell

clean:
	$(RM) *.o core $(EXE)

