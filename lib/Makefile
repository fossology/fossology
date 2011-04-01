# FOSSology Makefile - lib/libfossology
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
TOP=..
VARS=$(TOP)/Makefile.conf
include $(VARS)
CFLAGS_LOCAL=-fPIC $(ALL_CFLAGS)

CONFDEF=-DFOSSDB_CONF='"$(SYSCONFDIR)/$(PROJECT)/Db.conf"' -DFOSSREPO_CONF='"$(SYSCONFDIR)/fossology"' -DFOSSGROUP='"$(PROJECTGROUP)"'
EXE=reppath repwrite repcat repexist repcopyin repremove rephost repmmapcheck
REPMAINS=reppath.c repwrite.c repcat.c repexist.c repcopyin.c repremove.c rephost.c repmmapcheck.c
LIB=libfossology.a
OBJS=libfossscheduler.o libfossdb.o libfossagent.o libfossrepo.o 

all: $(OBJS) $(REPMAINS) $(LIB) $(EXE)

%.o: %.c %.h
	$(CC) -c $< $(CONFDEF)  -I`pg_config --includedir` -fPIC $(ALL_CFLAGS)

$(LIB): $(OBJS)
	$(AR) cr $@ $(OBJS)

$(EXE): $(REPMAINS)  $(LIB)
	$(CC) $< $(ALL_CFLAGS) $(LIB) -o $@

install: install-dev install-lib install-exe

install-dev: $(LIB)
	$(INSTALL_DATA) libfossology.a $(DESTDIR)$(LIBDIR)/libfossology.a
	$(INSTALL_DATA) libfossology.h $(DESTDIR)$(INCLUDEDIR)/libfossology.h

install-exe: $(EXE)
	for program in $(EXE); do \
	  $(INSTALL_PROGRAM) $$program $(DESTDIR)$(LIBEXECDIR)/$$program; \
	done

# If we ever do shared libs, they will go here
install-lib:

# uninstall only cleans up the files, not the directories that might have
# been created
uninstall: uninstall-dev uninstall-lib uninstall-exe

uninstall-dev:
	rm -f $(DESTDIR)$(LIBDIR)/libfossology.a
	rm -f $(DESTDIR)$(INCLUDEDIR)/libfossology.h

uninstall-exe:
	for program in $(EXE); do \
	  rm -f $(DESTDIR)$(LIBEXECDIR)/$$program; \
	done

uninstall-lib:

test: all
	@echo "*** No tests available for libfossology ***"

clean:
	rm -f $(LIB) $(EXE) *.o core

#.PHONY: all clean test
#.PHONY: install install-dev install-lib install-exe
#.PHONY: uninstall uninstall-dev uninstall-lib uninstall-exe
