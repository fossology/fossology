# FOSSology Makefile - devel/libfossrepo
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

CONFDEF=-DFOSSREPO_CONF='"$(SYSCONFDIR)/fossology"' -DFOSSGROUP='"$(PROJECTGROUP)"'
LIB=libfossrepo.a
EXE=reppath repwrite repcat repexist repcopyin repremove rephost repmmapcheck

all: $(LIB) $(EXE)

$(LIB): %.a: %.c %.h
	$(CC) -c $< $(CONFDEF) $(ALL_CFLAGS)
	$(AR) cr $@ libfossrepo.o

$(EXE): %: %.c $(LIB) $(VARS)
	$(CC) $< $(CFLAGS_REPO) -o $@

install: all $(VARS)
	$(INSTALL_DATA) libfossrepo.a $(DESTDIR)$(LIBDIR)/libfossrepo.a
	$(INSTALL_DATA) libfossrepo.h $(DESTDIR)$(INCLUDEDIR)/libfossrepo.h

	for program in $(EXE); do \
	   $(INSTALL_PROGRAM) $$program $(DESTDIR)$(LIBEXECDIR)/$$program; \
	done

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall:
	rm -f $(DESTDIR)$(LIBDIR)/libfossrepo.a
	rm -f $(DESTDIR)$(INCLUDEDIR)/libfossrepo.h

	for program in $(EXE); do \
	   rm -f $(DESTDIR)$(LIBEXECDIR)/$$program; \
	done

test: all
	@echo "*** No tests available for libfossrepo ***"

clean:
	rm -f $(LIB) $(EXE) *.o core

.PHONY: all install uninstall clean test
