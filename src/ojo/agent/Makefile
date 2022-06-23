# SPDX-FileCopyrightText: © 2019 Siemens AG

# SPDX-License-Identifier: FSFAP


TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DEF = -DDATADIR='"$(MODDIR)"'
CXXFLAGS_LOCAL = $(FO_CXXFLAGS) -I. -Wall -fopenmp \
                 $(shell pkg-config --cflags jsoncpp)

CXXFLAGS_LINK = $(FO_CXXLDFLAGS) -fopenmp -lboost_regex -lboost_system \
                -lboost_filesystem -lboost_program_options -lstdc++ \
                $(shell pkg-config --libs jsoncpp)

EXE = ojo

OBJECTS = OjosDatabaseHandler.o OjoState.o OjoAgent.o OjoUtils.o directoryScan.o ojoregex.o ojos.o
COVERAGE = $(OBJECTS:%.o=%_cov.o)

all: $(CXXFOLIB) $(EXE)

$(EXE): $(CXXFOLIB) $(VARS) ojoregex.hpp $(OBJECTS)
	$(CXX) $(OBJECTS) $(DEF) $(CXXFLAGS_LINK) -o $@

$(EXE)_cov: $(CXXFOLIB) $(VARS) $(COVERAGE)
	$(CXX) $(COVERAGE) $(FLAG_COV) $(DEF) $(CXXFLAGS_LINK) -o $@

#######################
# library build rules #
#######################

libojo.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libojo_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

$(CXXFOLIB):
	$(MAKE) -C $(CXXFOLIBDIR)

######################
# object build rules #
######################

$(OBJECTS): %.o: %.cc %.hpp
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF) $<

$(COVERAGE): %_cov.o: %.cc %.hpp
	$(CXX) -c $< $(CXXFLAGS_LOCAL) $(FLAG_COV) $(DEF) $(DEFS) -o $@

#######################
# install build rules #
#######################

install: $(EXE)
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o *.a *.gcno *.gcda core

.PHONY: all install uninstall clean
