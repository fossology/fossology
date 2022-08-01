# SPDX-FileCopyrightText: © 2019 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LOCALAGENTDIR = ../../agent
# -Werror -Wextra
CXXFLAGS_LOCAL = $(FO_CXXFLAGS) -I. -Wall -I$(LOCALAGENTDIR)
DEF = -DDATADIR='"$(MODDIR)"'
CONFDIR = $(DESTDIR)$(SYSCONFDIR)
CXXFLAGS_LINK = $(FO_CXXLDFLAGS) -lcppunit -lboost_regex 

EXE = test_ojo

OBJECTS = test_regex.o test_scanners.o
COVERAGE = $(OBJECTS:%.o=%_cov.o)

$(EXE): $(OBJECTS) libojo.a run_tests.cc
	$(CXX) run_tests.cc -o $@ $(OBJECTS) $(LOCALAGENTDIR)/libojo.a $(CXXFLAGS_LINK)

all: $(CXXFOLIB) $(EXE)

$(CXXFOLIB):
	$(MAKE) -C $(CXXFOLIBDIR)

libojo.a: $(OBJECTS)
	$(MAKE) -C $(LOCALAGENTDIR) $@

libojo_cov.a: $(COVERAGE)
	$(MAKE) -C $(LOCALAGENTDIR) $@

%.o: %.cc
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF) $<

$(COVERAGE): %_cov.o: %.c
	$(CXX) -c $< $(CXXFLAGS_LOCAL) $(FLAG_COV) $(DEF) $(DEFS) -o $@

test: all
	./$(EXE)

coverage: run_tests.cc $(OBJECTS) libojo_cov.a
	$(CXX) run_tests.cc -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/libojo_cov.a $(CFLAGS_LOCAL) $(CXXFLAGS_LINK) $(FLAG_COV); \
	./$(EXE)

clean:
	rm -rf $(EXE) $(EXE)_cov *.o *.a *.gcno *.gcda core results

.PHONY: all test coverage clean
