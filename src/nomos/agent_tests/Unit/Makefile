######################################################################
# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty
######################################################################
TOP = ../../../..
include $(TOP)/Makefile.conf
LOCALAGENTDIR=../../agent

TESTDIR = $(TOP)/src/testing/lib/c
TESTLIB = -L$(TESTDIR) -lfocunit -lcunit -I$(TESTDIR)
CFLAGS_LOCAL= $(ALL_CFLAGS) -I$(LOCALAGENTDIR) -lcunit $(TESTLIB) -std=c99
DEF = -DDATADIR='"$(DATADIR)"'
EXE = test_nomos

OBJECTS = test_nomos_gap.o  test_DoctoredBuffer.o \
          $(NULL)
# test_nomos_gap.o 
all: $(EXE)
	@echo "make nomos Unit agent_tests"

test: $(EXE)
	@echo "make nomos Unit agent_tests"
	./$(EXE)

coverage: agent run_tests.c $(OBJECTS) libnomos_cov.a ${FOLIB}
	@echo "make nomos agent_tests coverage"
	${MAKE} -C ${TESTDIR}
	$(CC) run_tests.c -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/libnomos_cov.a $(CFLAGS_LOCAL) $(FLAG_COV) ; \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR) --capture --output-file cov.txt; \
	genhtml  -o  results cov.txt

$(EXE): agent $(OBJECTS) libnomos.a run_tests.c ${FOLIB}
	${MAKE} -C ${TESTDIR}
	$(CC) run_tests.c -o $@ $(OBJECTS) $(LOCALAGENTDIR)/libnomos.a $(CFLAGS_LOCAL)

$(OBJECTS): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

agent:
	$(MAKE) -C $(LOCALAGENTDIR)

libnomos.a:
	$(MAKE) -C $(LOCALAGENTDIR) $@

libnomos_cov.a: 
	$(MAKE) -C $(LOCALAGENTDIR) $@

clean:
	@echo "make nomos agent_tests clean"
	rm -rf $(EXE) *.a *.o *.g *.xml *.txt *.gcda *.gcno results

install:
	@echo "make nomos agent_tests nothing to install"
uninstall:
	@echo "make nomos agent_tests nothing to uninstall"

.PHONY: all install uninstall clean test 
include ${TOP}/Makefile.deps

