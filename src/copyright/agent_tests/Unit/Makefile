# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LOCALAGENTDIR = ../../agent
TESTDIR = $(TOP)/src/testing/lib/c
TESTLIB = -L$(TESTDIR) -lstdc++ -lcppunit -ldl -I$(TESTDIR)
# -Werror -Wextra
CPPUNIT_PATH = /opt/cppunit
CXXFLAGS_LOCAL = $(ALL_CXXFLAGS) -I. -Wall -I${CPPUNIT_PATH}/include -I$(LOCALAGENTDIR) -fopenmp
DEF = -DDATADIR='"$(MODDIR)"'
CONFDIR = $(DESTDIR)$(SYSCONFDIR)
CXXFLAGS_LINK = -lboost_regex -lboost_program_options $(ALL_CXXFLAGS) -lm -L${CPPUNIT_PATH}/lib -lstdc++ -lcppunit -ldl -fopenmp

EXE = test_copyright

OBJECTS = test_regex.o test_RegMatcher.o test_testdataCheckFound.o
COVERAGE =

$(EXE): agent $(OBJECTS) libcopyright.a run_tests.cc ${FOLIB}
	${MAKE} -C ${TESTDIR}
	$(CXX) run_tests.cc -o $@ $(OBJECTS) $(LOCALAGENTDIR)/libcopyright.a $(CXXFLAGS_LINK)

all: $(CXXFOLIB) $(EXE)

$(CXXFOLIB):
	$(MAKE) -C $(CXXFOLIBDIR)

agent:
	$(MAKE) -C $(LOCALAGENTDIR)

libcopyright.a: $(OBJECTS)
	$(MAKE) -C $(LOCALAGENTDIR) $@

libcopyright_cov.a: $(COVERAGE)
	$(MAKE) -C $(LOCALAGENTDIR) $@

%.o: %.cc
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF) $<

$(COVERAGE): %_cov.o: %.c
	$(CXX) -c $< $(CXXFLAGS_LOCAL) $(FLAG_COV) $(DEF) $(DEFS) -o $@

test: all
	./$(EXE)

coverage: agent run_tests.cc $(OBJECTS) libcopyright_cov.a ${FOLIB}
	${MAKE} -C ${TESTDIR}
	$(CXX) run_tests.cc -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/libcopyright_cov.a $(CFLAGS_LOCAL) $(CXXFLAGS_LINK) $(FLAG_COV); \
	./$(EXE)

clean:
	rm -rf $(EXE) $(EXE)_cov *.o *.a *.gcno *.gcda core results

.PHONY: all test coverage clean
