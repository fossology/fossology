# FOSSology Makefile - devel/libfossagent
# Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) -fPIC $(ALL_CFLAGS)

LIB=libfossagent.a

all: $(LIB) $(VARS)

$(LIB): %.a: %.c %.h $(VARS)
	$(CC) -c $< $(CFLAGS_LOCAL)
	$(AR) cr $@ libfossagent.o

install: install-dev

install-dev: $(LIB)
	$(INSTALL_DATA) libfossagent.a $(DESTDIR)$(LIBDIR)/libfossagent.a
	$(INSTALL_DATA) libfossagent.h $(DESTDIR)$(INCLUDEDIR)/libfossagent.h

# If we ever do shared libs, they will go here
install-lib:

# libfossagent doesn't provide any executables
install-exe:

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall: uninstall-lib uninstall-exe uninstall-dev

uninstall-dev:
	rm -f $(DESTDIR)$(LIBDIR)/libfossagent.a
	rm -f $(DESTDIR)$(INCLUDEDIR)/libfossagent.h

uninstall-lib:
uninstall-exe:

test: all
	@echo "*** No tests available for libfossagent ***"

clean:
	rm -f $(LIB) *.o core

include $(DEPS)

.PHONY: all clean test
.PHONY: install install-lib install-dev install-exe
.PHONY: uninstall uninstall-lib uninstall-dev uninstall-exe

