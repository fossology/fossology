# libfossrepo Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CC=gcc
CFLAGS=-O -Wall -DFOSSREPO_CONF='"$(DATADIR)/repository"' -DFOSSGROUP='"$(PROJECTGROUP)"' $(CFLAGS2)
#CFLAGS=-g -Wall -lefence -DFOSSREPO_CONF='"$(DATADIR)/repository"' $(CFLAGS2)
LIB=libfossrepo.a
EXE=reppath repwrite repcat repexist repcopyin repremove rephost

all: $(LIB) $(EXE)

InstallationCreate:
	@for i in $(EXE) ; do echo "$(CP) $$i ../../install/$(LIBEXECDIR)/$$i" ; $(CP) $$i ../../install/$(LIBEXECDIR)/$$i ; done

install: all
	@for i in $(EXE) ; do echo "$(CP) $$i $(LIBEXECDIR)/$$i" ; $(CP) $$i $(LIBEXECDIR)/$$i ; chgrp osrb $(LIBEXECDIR)/$$i ; done
	#$(CP) libfossrepo.a $(LIBDIR)
	#$(CP) libfossrepo.h $(INCLUDEDIR)

uninstall:
	@for i in $(EXE) ; do echo "$(RM) $(BINDIR)/$$i" ; $(RM) $(BINDIR)/$$i ; done
	#$(RM) $(LIBDIR)/libfossrepo.a
	#$(RM) $(INCLUDEDIR)/libfossrepo.h

libfossrepo.a: libfossrepo.c libfossrepo.h
	$(RM) libfossrepo.a
	$(CC) $(CFLAGS) -c libfossrepo.c
	$(AR) cr libfossrepo.a libfossrepo.o
	$(CP) libfossrepo.a ../lib/
	$(CP) libfossrepo.h ../include/

reppath: reppath.c $(LIB)
	$(CC) $(CFLAGS) -o reppath reppath.c -L . -lfossrepo

rephost: rephost.c $(LIB)
	$(CC) $(CFLAGS) -o rephost rephost.c -L . -lfossrepo

repwrite: repwrite.c $(LIB)
	$(CC) $(CFLAGS) -o repwrite repwrite.c -L . -lfossrepo

repcat: repcat.c $(LIB)
	$(CC) $(CFLAGS) -o repcat repcat.c -L . -lfossrepo

repexist: repexist.c $(LIB)
	$(CC) $(CFLAGS) -o repexist repexist.c -L . -lfossrepo

repcopyin: repcopyin.c $(LIB)
	$(CC) $(CFLAGS) -o repcopyin repcopyin.c -L . -lfossrepo

repremove: repremove.c $(LIB)
	$(CC) $(CFLAGS) -o repremove repremove.c -L . -lfossrepo

clean:
	$(RM) $(LIB) $(EXE) *.o

