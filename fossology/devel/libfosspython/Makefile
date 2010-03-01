# FOSSology Makefile - devel/libfossdb
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

CONFDEF=-DFOSSDB_CONF='"$(SYSCONFDIR)/$(PROJECT)/Db.conf"'
LIB=libfosspython.so

all: $(LIB) $(VARS)

$(LIB): ../libfossagent/libfossagent.o ../libfossdb/libfossdb.o ../libfossrepo/libfossrepo.o
	python setup.py build_ext --inplace

../libfossagent/libfossagent.o:
	make -C ../libfossagent/ libfossagent.o
    
../libfossdb/libfossdb.o: 
	make -C ../libfossdb/ libfossdb.o 
    
../libfossrepo/libfossrepo.o:
	make -C ../libfossrepo/ libfossrepo.o

install: install-lib

install-lib: $(LIB)
	$(INSTALL_DATA) libfosspython.so $(DESTDIR)$(LIBDIR)/libfosspython.so

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall: uninstall-lib

uninstall-lib:
	rm -f $(DESTDIR)$(LIBDIR)/libfosspython.so

test: all
	@echo "*** No tests available for libfosspython ***"

clean:
	rm -f $(LIB) *.c core

.PHONY: all clean test
.PHONY: install install-lib uninstall uninstall-lib

# vim: noet
