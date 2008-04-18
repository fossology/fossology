# libfossdb Makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf
CC=gcc
CFLAGS=-DFOSSDB_CONF='"$(DATADIR)/dbconnect/$(PROJECT)"' -O2 -Wall $(CFLAGS2)
EXE=dbtest dbinit libfossdb.a dbq dbcheck

all: $(EXE)

libfossdb.a: libfossdb.c libfossdb.h
	$(RM) libfossdb.a
	$(CC) $(CFLAGS) -c libfossdb.c
	$(AR) cr libfossdb.a libfossdb.o
	if [ ! -d ../lib ] ; then mkdir ../lib ; fi
	if [ ! -d ../include ] ; then mkdir ../include ; fi
	$(CP) libfossdb.a ../lib/
	$(CP) libfossdb.h ../include/

dbtest: libfossdb.c libfossdb.h libfossdb.a
	$(CC) $(CFLAGS) dbtest.c -o dbtest -lpq -L. -lfossdb

dbcheck: libfossdb.c libfossdb.h libfossdb.a
	$(CC) $(CFLAGS) dbcheck.c -o dbcheck -lpq -L. -lfossdb

dbq: dbq.c libfossdb.h libfossdb.a
	$(CC) $(CFLAGS) dbq.c -o dbq -lpq -L. -lfossdb

dbinit: dbinit.c libfossdb.h libfossdb.a
	$(CC) $(CFLAGS) dbinit.c -o dbinit -lpq -L. -lfossdb

InstallationCreate:
	$(CP) dbcheck ../../install/$(AGENTTESTDDIR)/
	$(CP) dbinit ../../install/$(AGENTTESTDDIR)/
	$(CP) dbinit.sql ../../install/$(AGENTTESTDDIR)/
	$(CP) dbclear.sql ../../install/$(AGENTTESTDDIR)/

install: all
	#$(CP) libfossdb.a $(LIBDIR)
	#$(CP) libfossdb.h $(INCLUDEDIR)

uninstall:
	#$(RM) $(LIBDIR)/libfossdb.a
	#$(RM) $(INCLUDEDIR)/libfossdb.h

clean:
	$(RM) *.o core $(EXE)

