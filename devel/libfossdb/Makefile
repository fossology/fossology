# FOSSology Makefile - devel/libfossdb
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CFLAGS_LOCAL=-DFOSSDB_CONF="$(DATADIR)/dbconnect/$(PROJECT)" -lpq \
          -L. -lfossdb $(ALL_CFLAGS)
EXE=dbtest dbinit dbq dbcheck
LIB=libfossdb.a

all: $(LIB) $(EXE)

$(LIB): %.a: %.c %.h
	$(CC) -c $< $(ALL_CFLAGS)
	$(AR) cr $@ libfossdb.o

$(EXE): %: %.c $(LIB)
	$(CC) $< $(CFLAGS_LOCAL) -o $@

install: all
	$(INSTALL_DATA) libfossdb.a $(LIBDIR)/libfossdb.a
	$(INSTALL_DATA) libfossdb.h $(INCLUDEDIR)/libfossdb.h

	$(INSTALL_PROGRAM) dbcheck $(AGENTTESTDDIR)/dbcheck
	$(INSTALL_PROGRAM) dbinit $(AGENTTESTDDIR)/dbinit
	$(INSTALL_DATA) dbinit.sql $(AGENTTESTDDIR)/dbinit.sql
	$(INSTALL_DATA) dbclear.sql $(AGENTTESTDDIR)/dbclear.sql

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall:
	rm -f $(LIBDIR)/libfossdb.a
	rm -f $(INCLUDEDIR)/libfossdb.h

	rm -f $(AGENTTESTDDIR)/dbcheck
	rm -f $(AGENTTESTDDIR)/dbinit
	rm -f $(AGENTTESTDDIR)/dbinit.sql
	rm -f $(AGENTTESTDDIR)/dbclear.sql

test: all
	@echo "*** No tests available for libfossdb ***"

clean:
	rm -f $(LIB) $(EXE) *.o core

.PHONY: all install uninstall clean test
