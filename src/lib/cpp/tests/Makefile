# SPDX-FileCopyrightText: © 2014 Siemens AG

# SPDX-License-Identifier: FSFAP


TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

SRCDIR = ..

FO_LIBCDIR = ../../c

TESTDBDIR = $(TOP)/src/testing/db/c
TEST_CXXFLAGS = -I$(TESTDBDIR)
TEST_LDFLAGS = -L$(TESTDBDIR) -l fodbreposysconf

CXXFLAGS_LOCAL = $(TEST_CXXFLAGS) $(FO_CXXFLAGS) -Wall -I. -I$(SRCDIR)
CXXFLAGS_LINK  = $(TEST_LDFLAGS) -lcppunit $(FO_LDFLAGS)

EXE = test_libcpp

OBJECTS = test_fossdbmanagerclass.o
COVERAGE = $(OBJECTS:%.o=%_cov.o)

$(EXE): run_tests.cc $(OBJECTS) libfossologyCPP.a
	$(CXX) run_tests.cc -o $@ $(OBJECTS) $(SRCDIR)/libfossologyCPP.a $(CXXFLAGS_LINK) $(CXXFLAGS_LOCAL)

$(OBJECTS): %.o: %.cc
	$(CXX) -c $< $(CXXFLAGS_LOCAL) -o $@

$(COVERAGE): %_cov.o: %.cc
	$(CXX) -c $< $(CXXFLAGS_LOCAL) $(FLAG_COV) -o $@

libfossologyCPP.a:
	$(MAKE) -C $(SRCDIR) $@

libfossologyCPP_cov.a:
	$(MAKE) -C $(SRCDIR) $@

test: $(EXE)
	./$(EXE)

coverage: run_tests.cc $(COVERAGE) libfossologyCPP_cov.a
	$(CXX) run_tests.cc -o $(EXE) $(COVERAGE) $(SRCDIR)/libfossologyCPP_cov.a $(CXXFLAGS_LINK) $(FLAG_COV)
	./$(EXE)
	$(call coverage-report-html,$(SRCDIR))

clean:
	rm -rf $(EXE) *.o *.a *.gcno *.gcda *Tests-Results.xml cov.txt results

.PHONY: all test coverage clean

include $(DEPS)
