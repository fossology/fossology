# SPDX-FileCopyrightText: © 2014-2015 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DEF = -DDATADIR='"$(MODDIR)"'
CXXFLAGS_LOCAL = $(FO_CXXFLAGS) -I. -Wall -fopenmp
CXXFLAGS_LINK = $(FO_CXXLDFLAGS) -fopenmp

EXE = ninka

OBJECTS = databasehandler.o licensematch.o ninka.o ninkawrapper.o state.o utils.o
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

libninka.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libninka_cov.a: $(COVERAGE)
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
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o *.a *.gcno *.gcda core

.PHONY: all install uninstall clean
