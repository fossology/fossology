# SPDX-FileCopyrightText: © 2014, 2015 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = ${TOP}/Makefile.deps
include $(VARS)

LOCALAGENTDIR = ../../agent

TESTDIR = $(TOP)/src/testing/lib/c
TESTDBDIR = $(TOP)/src/testing/db/c
TEST_FLAGS = -L$(TESTDIR) -L$(TESTDBDIR) -l fodbreposysconf -l focunit
TEST_CFLAGS = -I $(TESTDIR) -I $(TESTDBDIR) -DCU_VERSION_P=$(CUNIT_VERSION)

LDFLAGS_LOCAL = $(FO_LDFLAGS) $(TEST_FLAGS) -lcunit -fopenmp -lm -Werror
CFLAGS_LOCAL = -std=c99 -I$(LOCALAGENTDIR) $(FO_CFLAGS) $(TEST_CFLAGS)

DEF = -DDATADIR='"$(DATADIR)"'
EXE = run_tests

ifeq (,$(shell pkg-config --exists uchardet || echo no))
LDFLAGS_LOCAL += $(shell pkg-config --libs uchardet)
CFLAGS_LOCAL += $(shell pkg-config --cflags uchardet)
CFLAGS_LOCAL += -DHAVE_CHARDET
else
LDFLAGS_LOCAL += -lmagic
endif

OBJECTS = test_string_operations.o \
          test_file_operations.o \
          test_license.o \
          test_highlight.o \
          test_hash.o \
          test_match.o \
          test_diff.o \
          test_database.o \
          test_encoding.o \
          test_serialize.o

all: $(EXE)

test: all
	./$(EXE)

testlib:
	$(MAKE) -C $(TESTDBDIR)
	$(MAKE) -C $(TESTDIR)

coverage: run_tests.c $(OBJECTS) libmonk_cov.a ${FOLIB} testlib
	${MAKE} -C ${TESTDIR}
	$(CC) run_tests.c -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/libmonk_cov.a $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL) $(FLAG_COV)
	./$(EXE)

$(EXE): $(OBJECTS) libmonk.a run_tests.c ${FOLIB} testlib
	${MAKE} -C ${TESTDIR}
	$(CC) run_tests.c -o $@ $(OBJECTS) $(LOCALAGENTDIR)/libmonk.a $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL)

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
