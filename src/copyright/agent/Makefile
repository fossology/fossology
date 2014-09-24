# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP=../../..
VARS=$(TOP)/Makefile.conf

include $(VARS)
# -Werror -Wextra
CXXFLAGS_LOCAL = $(ALL_CXXFLAGS) -I.  -Wall  -fopenmp
DEF          = -DDATADIR='"$(MODDIR)"'
CONFDIR      = $(DESTDIR)$(SYSCONFDIR)
CXXFLAGS_LINK  = -lboost_regex $(ALL_CXXFLAGS) -lm -fopenmp

EXE=copyright
TESTDIR = ../agent_tests
OBJECTS  = copyright.o copyrightState.o matcher.o regexMatcher.o  copyrightMatch.o database.o
COVERAGE = 

$(EXE): $(CXXFOLIB) $(VARS) $(OBJECTS)
	$(CXX) $(OBJECTS) $(DEF) $(CXXFLAGS_LINK) -o $@

all:  $(CXXFOLIB) $(EXE)

#######################
# library build rules #
#######################

$(CXXFOLIB):
	$(MAKE) -C $(CXXFOLIBDIR)

libcopyright.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)
	
libcopyright_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

######################
# object build rules #
######################

%.o: %.cc %.hpp
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
	$(MAKE) -C $(TESTDIR) $@

coverage: all
	$(MAKE) -C $(TESTDIR) $@

clean:
	rm -f $(EXE) *.o *.a *.gcno *.gcda core

.PHONY: all install uninstall clean test
