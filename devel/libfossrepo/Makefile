# FOSSology Makefile - devel/libfossrepo
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
include ../../Makefile.conf

CFLAGS_LOCAL=-DFOSSREPO_CONF="$(DATADIR)/repository" \
	-DFOSSGROUP="$(PROJECTGROUP)" -L. -lfossrepo $(ALL_CFLAGS)

LIB=libfossrepo.a
EXE=reppath repwrite repcat repexist repcopyin repremove rephost

all: $(LIB) $(EXE)

$(LIB): %.a: %.c %.h
	$(CC) -c $< $(ALL_CFLAGS)
	$(AR) cr $@ libfossrepo.o

$(EXE): %: %.c $(LIB)
	$(CC) $< $(CFLAGS_LOCAL) -o $@

install: all
	$(INSTALL_DATA) libfossrepo.a $(LIBDIR)/libfossrepo.a
	$(INSTALL_DATA) libfossrepo.h $(INCLUDEDIR)/libfossrepo.h

	for program in $(EXE); do \
	   $(INSTALL_PROGRAM) $$program $(LIBEXECDIR)/$$program; \
	done

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall:
	rm -f $(LIBDIR)/libfossrepo.a
	rm -f $(INCLUDEDIR)/libfossrepo.h

	for program in $(EXE); do \
	   rm -f $(LIBEXECDIR)/$$program; \
	done

test: all
	@echo "*** No tests available for libfossrepo ***"

clean:
	rm -f $(LIB) $(EXE) *.o core

.PHONY: all install uninstall clean test
