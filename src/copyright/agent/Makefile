# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

# -Werror -Wextra
CXXFLAGS_LOCAL = $(ALL_CXXFLAGS) -I. -Wall -Wextra -Weffc++ -fopenmp
DEF = -DDATADIR='"$(MODDIR)"'
CONFDIR = $(DESTDIR)$(SYSCONFDIR)
CXXFLAGS_LINK = -lboost_regex $(ALL_CXXFLAGS) -lm -fopenmp

TESTDIR = ../agent_tests

DEF_ID_COP = -DIDENTITY_COPYRIGHT
DEF_ID_ECC = -DIDENTITY_ECC
DEF_ID_IP = -DIDENTITY_IP

EXE_COP = copyright
EXE_IP = ip
EXE_ECC = ecc
EXE = $(EXE_COP) $(EXE_ECC) $(EXE_IP)

OBJECTS = copyright.o copyrightState.o matcher.o regexMatcher.o copyrightMatch.o regTypes.o cleanEntries.o database.o
OBJECTS_COP = copyrightUtils_cop.o
OBJECTS_IP = copyrightUtils_ip.o
OBJECTS_ECC = copyrightUtils_ecc.o

COVERAGE =

all: $(CXXFOLIB) $(EXE)

$(EXE_COP): $(CXXFOLIB) $(VARS) $(OBJECTS) $(OBJECTS_COP)
	$(CXX) $(OBJECTS) $(OBJECTS_COP) $(DEF_ID_COP) $(DEF) $(CXXFLAGS_LINK) -o $@

$(EXE_IP): $(CXXFOLIB) $(VARS) $(OBJECTS) $(OBJECTS_IP)
	$(CXX) $(OBJECTS) $(OBJECTS_IP) $(DEF_ID_IP) $(DEF) $(CXXFLAGS_LINK) -o $@

$(EXE_ECC): $(CXXFOLIB) $(VARS) $(OBJECTS) $(OBJECTS_ECC)
	$(CXX) $(OBJECTS) $(OBJECTS_ECC) $(DEF_ID_ECC) $(DEF) $(CXXFLAGS_LINK) -o $@

#######################
# library build rules #
#######################

$(CXXFOLIB):
	$(MAKE) -C $(CXXFOLIBDIR)

libcopyright.a: $(OBJECTS) $(OBJECT_COP)
	ar cvr $@ $(OBJECTS) $(OBJECT_COP)

libcopyright_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

######################
# object build rules #
######################

$(OBJECTS): %.o: %.cc %.hpp
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF) $<

$(OBJECTS_COP): %.o: $(OBJECTS_COP:_cop.o=.cc) $(OBJECTS_COP:_cop.o=.hpp)
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF_ID_COP) $(DEF) -o $(OBJECTS_COP) $<

$(OBJECTS_IP): %.o: $(OBJECTS_IP:_ip.o=.cc) $(OBJECTS_IP:_ip.o=.hpp)
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF_ID_IP) $(DEF) -o $(OBJECTS_IP) $<

$(OBJECTS_ECC): %.o: $(OBJECTS_ECC:_ecc.o=.cc) $(OBJECTS_ECC:_ecc.o=.hpp)
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF_ID_ECC) $(DEF) -o $(OBJECTS_ECC) $<

$(COVERAGE): %_cov.o: %.cc %.hpp
	$(CXX) -c $< $(CXXFLAGS_LOCAL) $(FLAG_COV) $(DEF_ID_COP) $(DEF) $(DEFS) -o $@

#######################
# install build rules #
#######################

install: $(EXE)
	for mod_name in $(EXE); do \
		echo "make $$mod_name agent install"; \
		$(INSTALL_PROGRAM) $$mod_name $(DESTDIR)$(MODDIR)/$$mod_name/agent/$$mod_name; \
	done

uninstall:
	for mod_name in $(EXE); do \
		echo "make $$mod_name agent uninstall"; \
		rm -rf $(DESTDIR)$(MODDIR)/$$mod_name/agent; \
	done

test: all
	$(MAKE) -C $(TESTDIR) $@

coverage: all
	$(MAKE) -C $(TESTDIR) $@

clean:
	rm -f $(EXE) *.o *.a *.gcno *.gcda core

.PHONY: all install uninstall clean test
