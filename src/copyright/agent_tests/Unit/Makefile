# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP=../../../..
VARS=$(TOP)/Makefile.conf
LOCALAGENTDIR=../../agent
TESTDIR = $(TOP)/src/testing/lib/c
TESTLIB = -L$(TESTDIR) -lstdc++ -lcppunit -ldl -I$(TESTDIR)
include $(VARS)
# -Werror -Wextra
CPPUNIT_PATH=/opt/cppunit
CXXFLAGS_LOCAL = $(ALL_CXXFLAGS) -I.  -Wall -I${CPPUNIT_PATH}/include   -I$(LOCALAGENTDIR) 
DEF          = -DDATADIR='"$(MODDIR)"'
CONFDIR      = $(DESTDIR)$(SYSCONFDIR)
CXXFLAGS_LINK  = -lboost_regex $(ALL_CXXFLAGS) -lm -L${CPPUNIT_PATH}/lib -lstdc++ -lcppunit -ldl

EXE=test_copyright

OBJECTS  = test_regex.o test_RegMatcher.o
COVERAGE = 



$(EXE): agent $(OBJECTS) libcopyright.a run_tests.cc ${FOLIB}
	${MAKE} -C ${TESTDIR}
	$(CXX) run_tests.cc -o $@ $(OBJECTS) $(LOCALAGENTDIR)/libcopyright.a $(CXXFLAGS_LINK)

all:  $(CXXFOLIB) $(EXE)

#######################
# library build rules #
#######################

$(CXXFOLIB):
	$(MAKE) -C $(CXXFOLIBDIR)


	
agent:
	$(MAKE) -C $(LOCALAGENTDIR)
	
libcopyright.a: $(OBJECTS)
	$(MAKE) -C $(LOCALAGENTDIR) $@	
	
libcopyright_cov.a: $(COVERAGE)
	$(MAKE) -C $(LOCALAGENTDIR) $@

######################
# object build rules #
######################

%.o: %.cc
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF) $<
	
$(COVERAGE): %_cov.o: %.c 
	$(CXX) -c $< $(CXXFLAGS_LOCAL) $(FLAG_COV) $(DEF) $(DEFS) -o $@

#######################
# install build rules #
#######################

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)
	@mkdir -p $(CONFDIR)/mods-enabled
	@if test ! -e $(CONFDIR)/mods-enabled/$(EXE); then \
	  ln -s $(MODDIR)/$(EXE) $(CONFDIR)/mods-enabled ; fi;

uninstall:
	@rm -rf $(DESTDIR)$(MODDIR)/$(EXE)
	@rm -f $(CONFDIR)/mods-enabled/$(EXE)


test: all
	./$(EXE)

coverage: agent run_tests.cc $(OBJECTS) libcopyright_cov.a ${FOLIB}
	@echo "make copyright agent_tests coverage"
	${MAKE} -C ${TESTDIR}
	$(CXX) run_tests.cc -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/libcopyright_cov.a $(CFLAGS_LOCAL) $(FLAG_COV) ; \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR) --capture --output-file cov.txt; \
	genhtml  -o  results cov.txt

clean:
	rm -f $(EXE) *.o *.a *.gcno *.gcda core

.PHONY: all install uninstall clean test
