# SPDX-FileCopyrightText: © 2012 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

SRCDIR = ..

TESTDIR = $(TOP)/src/testing/lib/c
TESTDBDIR = $(TOP)/src/testing/db/c
TEST_CFLAGS = -I $(TESTDIR) -I $(TESTDBDIR) -DCU_VERSION_P=$(CUNIT_VERSION)
TEST_LDFLAGS = -L$(TESTDIR) -L$(TESTDBDIR) -l fodbreposysconf -l focunit

CFLAGS_LOCAL = $(FO_CFLAGS) $(TEST_CFLAGS) -I$(SRCDIR) -I../
LDFLAGS_LOCAL = $(TEST_LDFLAGS) $(FO_LDFLAGS) -lcunit

EXE = testlibs

OBJS = test_fossconfig.o \
       test_fossscheduler.o \
       test_libfossdb.o \
       test_libfossdbmanager.o

all: test
test: $(EXE)
	@echo "running library tests"
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) testClib.php

testlib:
	$(MAKE) -C $(TESTDBDIR)
	$(MAKE) -C $(TESTDIR)

coverage: $(OBJS) libfossology_cov.a $(EXE).c $(FOLIB)
	@echo "make library coverage tests"
	$(CC) $(EXE).c -o $(EXE) $(OBJS) $(SRCDIR)/libfossology_cov.a $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL) $(FLAG_COV)
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) testClib.php
	$(call coverage-report-html,$(SRCDIR))

$(EXE): $(OBJS) libfossology.a $(EXE).c $(FOLIB) testlib
	$(CC) $(EXE).c -o $@ $(OBJS) $(SRCDIR)/libfossology.a $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL)

$(OBJS): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

libfossology.a:
	$(MAKE) -C $(SRCDIR) $@

libfossology_cov.a:
	$(MAKE) -C $(SRCDIR) $@

#####################
# other build rules #
#####################

clean:
	@echo "make library tests clean"
	rm -rf $(EXE) *.o *.g *.xml *.txt *.gcda *.gcno *.a results

install:
	@echo "make library tests nothing to install"

uninstall:
	@echo "make library tests nothing to uninstall"

.PHONY: all install uninstall clean test

include $(DEPS)
