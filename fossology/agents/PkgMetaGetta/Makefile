# PkgMetaGetta Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc
CFLAGS=-Wall -O2 $(CFLAGS2)
#CFLAGS=-Wall -g -lefence $(CFLAGS2)
EXE=pkgmetagetta
INC=-I../../$(BUILDINC)
LIB=-L../../$(BUILDLIB) -lfossrepo -lfossdb -lpq -lextractor

all: $(EXE)

pkgmetagetta: pkgmetagetta.c
	$(CC) $(CFLAGS) pkgmetagetta.c -o pkgmetagetta $(INC) $(LIB)

InstallationCreate:
	$(CP) pkgmetagetta ../../install/$(AGENTDIR)

install: all
	$(CP) pkgmetagetta $(AGENTDIR)

uninstall:
	$(RM) $(AGENTDIR)/pkgmetagetta

clean:
	$(RM) *.o core $(EXE)

