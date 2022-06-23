# SPDX-FileCopyrightText: © 2014 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = ${TOP}/Makefile.deps
include $(VARS)

LOCALAGENTDIR = ../../agent

TESTDIR = $(TOP)/src/testing/lib/c
TESTLIB = -L$(TESTDIR) -lfocunit -lcunit 
CFLAGS_LOCAL = $(FO_CFLAGS) -I$(LOCALAGENTDIR) -I$(TESTDIR) -std=c99 -DCU_VERSION_P=$(CUNIT_VERSION)
LDFLAGS_LOCAL = $(FO_LDFLAGS) -lcunit $(TESTLIB) $(shell pkg-config --libs json-c) -lpthread -lrt
DEF = -DDATADIR='"$(DATADIR)"'
EXE = test_nomos

OBJECTS = test_nomos_gap.o test_DoctoredBuffer.o 

# test_nomos_gap.o
all: $(EXE)

test: all
	./$(EXE)

coverage: agent run_tests.c $(OBJECTS) libnomos_cov.a ${FOLIB}
	${MAKE} -C ${TESTDIR}
	$(CC) run_tests.c -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/libnomos_cov.a $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL) $(FLAG_COV); \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR) --capture --output-file cov.txt; \
	genhtml -o results cov.txt

$(EXE): agent $(OBJECTS) libnomos.a run_tests.c ${FOLIB}
	${MAKE} -C ${TESTDIR}
	$(CC) run_tests.c -o $@ $(OBJECTS) $(LOCALAGENTDIR)/libnomos.a $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL)

$(OBJECTS): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

agent:
	$(MAKE) -C $(LOCALAGENTDIR)

libnomos.a:
	$(MAKE) -C $(LOCALAGENTDIR) $@

libnomos_cov.a:
	$(MAKE) -C $(LOCALAGENTDIR) $@

clean:
	rm -rf $(EXE) *.a *.o *.g *.xml *.txt *.gcda *.gcno results

.PHONY: all test coverage clean

include ${DEPS}
