# FOSSology Makefile - devel/libfossdb
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

CONFDEF=-DFOSSDB_CONF='"$(SYSCONFDIR)/$(PROJECT)/Db.conf"'
EXE=dbtest dbq dbcheck
LIB=libfossdb.a

all: $(LIB) $(EXE) $(VARS)

$(LIB): %.a: %.c %.h $(VARS)
	$(CC) -c $< $(CONFDEF) -I`pg_config --includedir` $(ALL_CFLAGS)
	$(AR) cr $@ libfossdb.o

$(EXE): %: %.c $(LIB) $(VARS)
	$(CC) $< $(CFLAGS_DB) -lpq -o $@

install: install-lib install-exe

install-lib: $(LIB)
	$(INSTALL_DATA) libfossdb.a $(DESTDIR)$(LIBDIR)/libfossdb.a
	$(INSTALL_DATA) libfossdb.h $(DESTDIR)$(INCLUDEDIR)/libfossdb.h

install-exe: $(EXE)
	$(INSTALL_PROGRAM) dbcheck $(DESTDIR)$(AGENTTESTDDIR)/dbcheck

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall: uninstall-lib uninstall-exe

uninstall-lib:
	rm -f $(DESTDIR)$(LIBDIR)/libfossdb.a
	rm -f $(DESTDIR)$(INCLUDEDIR)/libfossdb.h

uninstall-exe:
	rm -f $(DESTDIR)$(AGENTTESTDDIR)/dbcheck

test: all
	@echo "*** No tests available for libfossdb ***"

clean:
	rm -f $(LIB) $(EXE) *.o core

.PHONY: all clean test
.PHONY: install install-lib install-exe uninstall uninstall-lib uninstall-exe
