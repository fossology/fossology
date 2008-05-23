# FOSSology Makefile - devel/libfossdb
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

CFLAGS_LOCAL=-DFOSSDB_CONF="$(DATADIR)/dbconnect/$(PROJECT)" -lpq \
          -L. -lfossdb $(ALL_CFLAGS)
EXE=dbtest dbinit dbq dbcheck
LIB=libfossdb.a

all: $(LIB) $(EXE)

$(LIB): %.a: %.c %.h $(VARS)
	$(CC) -c $< $(ALL_CFLAGS)
	$(AR) cr $@ libfossdb.o

$(EXE): %: %.c $(LIB) $(VARS)
	$(CC) $< $(CFLAGS_LOCAL) -o $@

install: all $(VARS)
	$(INSTALL_DATA) libfossdb.a $(DESTDIR)$(LIBDIR)/libfossdb.a
	$(INSTALL_DATA) libfossdb.h $(DESTDIR)$(INCLUDEDIR)/libfossdb.h

	$(INSTALL_PROGRAM) dbcheck $(DESTDIR)$(AGENTTESTDDIR)/dbcheck
	$(INSTALL_PROGRAM) dbinit $(DESTDIR)$(AGENTTESTDDIR)/dbinit
	$(INSTALL_DATA) dbinit.sql $(DESTDIR)$(AGENTTESTDDIR)/dbinit.sql
	$(INSTALL_DATA) dbclear.sql $(DESTDIR)$(AGENTTESTDDIR)/dbclear.sql

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall:
	rm -f $(DESTDIR)$(LIBDIR)/libfossdb.a
	rm -f $(DESTDIR)$(INCLUDEDIR)/libfossdb.h

	rm -f $(DESTDIR)$(AGENTTESTDDIR)/dbcheck
	rm -f $(DESTDIR)$(AGENTTESTDDIR)/dbinit
	rm -f $(DESTDIR)$(AGENTTESTDDIR)/dbinit.sql
	rm -f $(DESTDIR)$(AGENTTESTDDIR)/dbclear.sql

test: all
	@echo "*** No tests available for libfossdb ***"

clean:
	rm -f $(LIB) $(EXE) *.o core

.PHONY: all install uninstall clean test
