# SPDX-FileCopyrightText: © 2014-2015 Siemens AG

# SPDX-License-Identifier: FSFAP
TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LOCALAGENTDIR = ../../agent
# -Werror -Wextra
CXXFLAGS_LOCAL = $(FO_CXXFLAGS) -I. -Wall -I$(LOCALAGENTDIR) -fopenmp \
                $(shell pkg-config --cflags jsoncpp icu-uc)
DEF = -DDATADIR='"$(MODDIR)"'
CONFDIR = $(DESTDIR)$(SYSCONFDIR)
CXXFLAGS_LINK = -lboost_regex -lboost_program_options $(FO_CXXLDFLAGS) -lm \
                -lstdc++ -lcppunit -ldl -fopenmp \
                $(shell pkg-config --libs icu-uc)

EXE = test_copyright

OBJECTS = test_regex.o test_scanners.o test_regexConfProvider.o
OBJECTS_ACC = test_accuracy.o
COVERAGE =

$(EXE): agent $(OBJECTS) libcopyright.a run_tests.cc
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

coverage: agent run_tests.cc $(OBJECTS) libcopyright_cov.a
	$(CXX) run_tests.cc -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/libcopyright_cov.a $(CFLAGS_LOCAL) $(CXXFLAGS_LINK) $(FLAG_COV); \
	./$(EXE)

accuracy: agent run_tests.cc $(OBJECTS_ACC) libcopyright.a
	$(CXX) run_tests.cc -o $(EXE) $(OBJECTS_ACC) $(LOCALAGENTDIR)/libcopyright.a $(CFLAGS_LOCAL) $(CXXFLAGS_LINK); \
	./$(EXE)

clean:
	rm -rf $(EXE) $(EXE)_cov *.o *.a *.gcno *.gcda core results

.PHONY: all test coverage clean
