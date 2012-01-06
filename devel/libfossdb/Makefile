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
	$(CC) -c $< $(CONFDEF) -I`pg_config --includedir` -fPIC $(ALL_CFLAGS)
	$(AR) cr $@ libfossdb.o

$(EXE): %: %.c $(LIB) $(VARS)
	$(CC) $< $(CFLAGS_DB) -I`pg_config --includedir` -lpq -o $@

install: install-dev install-lib install-exe

install-dev: $(LIB)
	$(INSTALL_DATA) libfossdb.a $(DESTDIR)$(LIBDIR)/libfossdb.a
	$(INSTALL_DATA) libfossdb.h $(DESTDIR)$(INCLUDEDIR)/libfossdb.h

install-exe: $(EXE)
	$(INSTALL_PROGRAM) dbcheck $(DESTDIR)$(LIBEXECDIR)/dbcheck

# If we ever do shared libs, they will go here
install-lib:

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall: uninstall-dev uninstall-lib uninstall-exe

uninstall-dev:
	rm -f $(DESTDIR)$(LIBDIR)/libfossdb.a
	rm -f $(DESTDIR)$(INCLUDEDIR)/libfossdb.h

uninstall-exe:
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbcheck

uninstall-lib:

test: all
	@echo "*** No tests available for libfossdb ***"

clean:
	rm -f $(LIB) $(EXE) *.o core

.PHONY: all clean test
.PHONY: install install-dev install-lib install-exe
.PHONY: uninstall uninstall-dev uninstall-lib uninstall-exe
