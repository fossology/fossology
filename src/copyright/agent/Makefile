# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP=../../..
VARS=$(TOP)/Makefile.conf

include $(VARS)

CFLAGS_LOCAL =  $(ALL_CFLAGS) -I. -Werror -Wall -Wextra -fopenmp
DEF          = -DDATADIR='"$(MODDIR)"'
CONFDIR      = $(DESTDIR)$(SYSCONFDIR)
CFLAGS_LINK  = $(ALL_CFLAGS) -lm -fopenmp

CXX  = g++

EXE=copyright
TESTDIR = ../agent_tests
OBJECTS  = copyright.o copyrightState.o
COVERAGE = 

$(EXE): $(FOLIB) $(VARS) $(OBJECTS)
	$(CXX) $(OBJECTS) $(DEF) $(CFLAGS_LINK) -o $@

all:  $(FOLIB) $(EXE)

#######################
# library build rules #
#######################

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

libcopyright.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)
	
libcopyright_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

######################
# object build rules #
######################

copyright.o: %.o: %.cc %.h
	$(CXX) -c $(CFLAGS_LOCAL) $(DEF) $<

copyrightState.o: %.o: %.cc %.h
	$(CXX) -c $(CFLAGS_LOCAL) $(DEF) $<
	
$(COVERAGE): %_cov.o: %.c 
	$(CXX) -c $< $(CFLAGS_LOCAL) $(FLAG_COV) $(DEF) $(DEFS) -o $@

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
