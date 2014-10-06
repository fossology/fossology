# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../../..
include $(TOP)/Makefile.conf
LOCALAGENTDIR = ../../agent

TESTDIR = $(TOP)/src/testing/lib/c
TESTLIB = -L$(TESTDIR) -l focunit -I $(TESTDIR)
CFLAGS_LOCAL = -std=c99 $(ALL_CFLAGS) -I$(LOCALAGENTDIR) -lcunit -lm $(TESTLIB) -Werror -fopenmp
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

all:

test: $(EXE)
	@echo "make monk Unit agent_tests"
	./$(EXE)

coverage: run_tests.c $(OBJECTS) libmonk_cov.a ${FOLIB}
	@echo "make monk agent_tests coverage"
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
	@echo "make monk agent_tests clean"
	rm -rf $(EXE) *.a *.o *.g *.xml *.txt *.gcda *.gcno results

install:
	@echo "make monk agent_tests nothing to install"
uninstall:
	@echo "make monk agent_tests nothing to uninstall"

.PHONY: all install uninstall clean test coverage
include ${TOP}/Makefile.deps

