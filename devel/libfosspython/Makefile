# FOSSology Makefile - devel/libfossdb
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)
DEPS=$(TOP)/Makefile.deps

CONFDEF=-DFOSSDB_CONF='"$(SYSCONFDIR)/$(PROJECT)/Db.conf"'
LIB=libfosspython.so

all: $(LIB) $(VARS)

$(LIB): $(DB) $(REPO) $(AGENTLIB) libfosspython.pyx
	python setup.py build_ext --inplace

install: install-dev install-lib install-exe

install-lib: $(LIB)
	mkdir -p $(DESTDIR)$(PYTHONLIBEXECDIR)
	$(INSTALL_DATA) libfosspython.so $(DESTDIR)$(PYTHONLIBEXECDIR)/libfosspython.so

# libfosspython doesn't provide development specific resources
install-dev:

# libfosspython doesn't provide any executables
install-exe:

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall: uninstall-dev uninstall-exe uninstall-lib

uninstall-lib:
	rm -f $(DESTDIR)$(PYTHONLIBEXECDIR)/libfosspython.so
	if [ -d "$(DESTDIR)$(PYTHONLIBEXECDIR)" ]; then \
	   rmdir $(DESTDIR)$(PYTHONLIBEXECDIR);\
        fi


uninstall-dev:
uninstall-exe:

test: all
	@echo "*** No tests available for libfosspython ***"

clean:
	rm -f $(LIB) *.c core
	rm -rf build

.PHONY: all clean test
.PHONY: install install-dev install-lib install-exe
.PHONY: uninstall uninstall-dev uninstall-lib uninstall-exe

include $(DEPS)

# vim: noet
