# FOSSology Makefile - devel/libfossdb
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)
DEPS=$(TOP)/Makefile.deps

CONFDEF=-DFOSSDB_CONF='"$(SYSCONFDIR)/$(PROJECT)/Db.conf"'
LIB=libfosspython.so

all: $(LIB) $(VARS)

$(LIB): $(DB) $(REPO) $(AGENTLIB)
	python setup.py build_ext --inplace

install: install-lib

install-lib: $(LIB)
	mkdir -p $(DESTDIR)$(LIBDIR)/libfosspython/
	$(INSTALL_DATA) libfosspython.so $(DESTDIR)$(LIBDIR)/libfosspython/libfosspython.so

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall: uninstall-lib

uninstall-lib:
	rm -f $(DESTDIR)$(LIBDIR)/libfosspython/libfosspython.so
	rmdir $(DESTDIR)$(LIBDIR)/libfosspython/

test: all
	@echo "*** No tests available for libfosspython ***"

clean:
	rm -f $(LIB) *.c core

.PHONY: all clean test
.PHONY: install install-lib uninstall uninstall-lib

include $(DEPS)

# vim: noet
