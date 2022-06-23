# SPDX-FileCopyrightText: © 2021 Sarita Singh <saritasingh.0425@gmail.com>

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DEF = -DDATADIR='"$(MODDIR)"'
CXXFLAGS_LOCAL = $(FO_CXXFLAGS) -I. -Wall -fopenmp $(shell pkg-config --cflags jsoncpp)
CXXFLAGS_LINK = $(FO_CXXLDFLAGS) -lboost_program_options -lboost_system -fopenmp $(shell pkg-config --libs jsoncpp)

EXE = scancode

OBJECTS = match.o scancode_dbhandler.o scancode.o scancode_wrapper.o scancode_state.o scancode_utils.o
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

libscancode.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)

libscancode_cov.a: $(COVERAGE)
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
	$(INSTALL_DATA) ./scancode_template.html $(DESTDIR)$(MODDIR)/$(EXE)/agent/scancode_template.html

uninstall:
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent

clean:
	rm -f $(EXE) *.o *.a *.gcno *.gcda core

.PHONY: all install uninstall clean
