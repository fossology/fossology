# Copyright Siemens AG 2019-2020
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

MOD_NAME = atarashiWrapper
DEF = -DDATADIR='"$(MODDIR)"'
CXXFLAGS_LOCAL = $(FO_CXXFLAGS) -I. -Wall -fopenmp $(shell pkg-config --cflags jsoncpp) 
CXXFLAGS_LINK = $(FO_CXXLDFLAGS) -fopenmp $(shell pkg-config --libs jsoncpp)

EXE = atarashiWrapper

OBJECTS = databasehandler.o licensematch.o atarashi.o atarashiwrapper.o state.o utils.o
COVERAGE = $(OBJECTS:%.o=%_cov.o)

all: $(CXXFOLIB) $(EXE)

$(EXE): $(CXXFOLIB) $(VARS) $(OBJECTS)
	$(CXX) $(OBJECTS) $(DEF) $(CXXFLAGS_LINK) -o $@

$(EXE)_cov: $(CXXFOLIB) $(VARS) $(COVERAGE)
	$(CXX) $(COVERAGE) $(FLAG_COV) $(DEF) $(CXXFLAGS_LINK) -o $@

#######################
# library build rules #
#######################

$(CXXFOLIB):
	$(MAKE) -C $(CXXFOLIBDIR)

libatarashi.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libatarashi_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

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
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(MOD_NAME)/agent/$(EXE)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o *.a *.gcno *.gcda core

.PHONY: all install uninstall clean
