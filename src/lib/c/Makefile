# FOSSology Makefile - lib/libfossology
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)
CFLAGS_LOCAL=-fPIC $(ALL_CFLAGS) $(CFLAGS_FO)

CONFDEF=-DFOSSDB_CONF='"$(SYSCONFDIR)/Db.conf"' -DFOSSGROUP='"$(PROJECTGROUP)"' \
        -DDEFAULT_SETUP='"$(SYSCONFDIR)"'
EXE=sqlCopyTest fossconfigTest reppath
LIB=libfossology.a
OBJS=libfossscheduler.o libfossdb.o libfossagent.o libfossrepo.o sqlCopy.o fossconfig.o
COVERAGE=$(OBJS:%.o=%_cov.o)

all: $(LIB) $(VARS) $(EXE)
	echo "make $(LIB)"

$(LIB): $(OBJS) $(VARS)
	$(AR) cr $@ $(OBJS)
#	$(CC) -c $< $(CONFDEF) -fPIC $(ALL_CFLAGS)

libfossology_cov.a: $(COVERAGE)
	$(AR) cr $@ $(COVERAGE)

$(EXE): %: %.c $(LIB) $(VARS)
	$(CC) $< $(CONFDEF) $(CFLAGS_LOCAL) $(LIB) -o $@

%.o: %.c %.h
	$(CC) -c $< $(CONFDEF)  $(CFLAGS_LOCAL)
#	$(CC) -c $< $(CONFDEF)  -I`pg_config --includedir` -fPIC 

$(COVERAGE): %_cov.o: %.c
	$(CC) -c $< $(CONFDEF)  $(CFLAGS_LOCAL) $(FLAG_COV) -o $@

install: install-dev install-lib install-exe
	echo "make $(LIB) install"

install-dev: $(LIB)
	$(INSTALL_DATA) libfossology.a $(DESTDIR)$(LIBEXECDIR)/libfossology.a
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
	echo "make $(LIB) uninstall"

uninstall-dev:
	rm -f $(DESTDIR)$(LIBEXECDIR)/libfossology.a
	rm -f $(DESTDIR)$(INCLUDEDIR)/libfossology.h

uninstall-exe:
	for program in $(EXE); do \
	  rm -f $(DESTDIR)$(LIBEXECDIR)/$$program; \
	done

uninstall-lib:

test: all
	$(MAKE) -C tests

clean:
	echo "make $(LIB) clean "
	rm -f *.a $(EXE) *.o core *.gc*

.PHONY: all clean test
.PHONY: install-dev install-lib install-exe
.PHONY: uninstall-dev uninstall-lib uninstall-exe
