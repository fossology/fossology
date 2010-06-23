# FOSSology Makefile - devel/libfossrepo
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

CONFDEF=-DFOSSREPO_CONF='"$(SYSCONFDIR)/fossology"' -DFOSSGROUP='"$(PROJECTGROUP)"'
LIB=libfossrepo.a
EXE=reppath repwrite repcat repexist repcopyin repremove rephost repmmapcheck

all: $(LIB) $(EXE) $(VARS)

$(LIB): %.a: %.c %.h
	$(CC) -c $< $(CONFDEF) -fPIC $(ALL_CFLAGS)
	$(AR) cr $@ libfossrepo.o

$(EXE): %: %.c $(LIB) $(VARS)
	$(CC) $< $(CFLAGS_REPO) -o $@

install: install-dev install-lib install-exe

install-dev:  $(LIB)
	$(INSTALL_DATA) libfossrepo.a $(DESTDIR)$(LIBDIR)/libfossrepo.a
	$(INSTALL_DATA) libfossrepo.h $(DESTDIR)$(INCLUDEDIR)/libfossrepo.h

install-exe: $(EXE)
	for program in $(EXE); do \
	   $(INSTALL_PROGRAM) $$program $(DESTDIR)$(LIBEXECDIR)/$$program; \
	done

# If we ever do shared libs, they will go here
install-libs:

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall: uninstall-dev uninstall-lib uninstall-exe

uninstall-dev:
	rm -f $(DESTDIR)$(LIBDIR)/libfossrepo.a
	rm -f $(DESTDIR)$(INCLUDEDIR)/libfossrepo.h

uninstall-exe:
	for program in $(EXE); do \
	   rm -f $(DESTDIR)$(LIBEXECDIR)/$$program; \
	done

uninstall-lib:

test: all
	@echo "*** No tests available for libfossrepo ***"

clean:
	rm -f $(LIB) $(EXE) *.o core

.PHONY: all clean test
.PHONY: install install-dev install-lib install-exe
.PHONY: uninstall uninstall-dev uninstall-lib uninstall-exe
