# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = ${TOP}/Makefile.deps
include $(VARS)

LOCALAGENTDIR = ../../agent

TESTDIR = $(TOP)/src/testing/lib/c
TESTLIB = -L$(TESTDIR) -l focunit -I $(TESTDIR)
CFLAGS_LOCAL = -std=c99 $(ALL_CFLAGS) -I$(LOCALAGENTDIR) -lcunit -fopenmp -lm $(TESTLIB) -Werror
DEF = -DDATADIR='"$(DATADIR)"'
EXE = test_monk

OBJECTS = test_string_operations.o \
          test_file_operations.o \
          test_license.o \
          test_highlight.o \
          test_hash.o \
          test_match.o \
          test_extended.o \
          test_diff.o \
          test_database.o \
          utils.o

all: $(EXE)

test: all
	./$(EXE)

coverage: run_tests.c $(OBJECTS) libmonk_cov.a ${FOLIB}
	${MAKE} -C ${TESTDIR}
	$(CC) run_tests.c -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/libmonk_cov.a $(CFLAGS_LOCAL) $(FLAG_COV); \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR) --capture --output-file cov.txt; \
	genhtml -o results cov.txt

$(EXE): $(OBJECTS) libmonk.a run_tests.c ${FOLIB}
	${MAKE} -C ${TESTDIR}
	$(CC) run_tests.c -o $@ $(OBJECTS) $(LOCALAGENTDIR)/libmonk.a $(CFLAGS_LOCAL)

$(OBJECTS): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

libmonk.a:
	$(MAKE) -C $(LOCALAGENTDIR) $@

libmonk_cov.a:
	$(MAKE) -C $(LOCALAGENTDIR) $@

clean:
	rm -rf $(EXE) *.a *.o *.g *.xml *.txt *.gcda *.gcno results

.PHONY: all test coverage clean

include ${DEPS}
