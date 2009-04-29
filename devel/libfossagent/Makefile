# FOSSology Makefile - devel/libfossagent
# Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(ALL_CFLAGS)

LIB=libfossagent.a

all: $(LIB) $(VARS)

$(LIB): %.a: %.c %.h $(VARS)
	$(CC) -c $< $(CFLAGS_LOCAL)
	$(AR) cr $@ libfossagent.o

install: install-lib 

install-lib: $(LIB)
	$(INSTALL_DATA) libfossagent.a $(DESTDIR)$(LIBDIR)/libfossagent.a
	$(INSTALL_DATA) libfossagent.h $(DESTDIR)$(INCLUDEDIR)/libfossagent.h


# uninstall only cleans up the files, not the directories that might have
# been created
uninstall: uninstall-lib 

uninstall-lib:
	rm -f $(DESTDIR)$(LIBDIR)/libfossagent.a
	rm -f $(DESTDIR)$(INCLUDEDIR)/libfossagent.h


test: all
	@echo "*** No tests available for libfossagent ***"

clean:
	rm -f $(LIB) *.o core

include $(DEPS)

.PHONY: all clean test
.PHONY: install install-lib uninstall uninstall-lib 

