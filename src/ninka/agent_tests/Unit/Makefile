# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

LOCALAGENTDIR = ../../agent
DEF = -DDATADIR='"$(MODDIR)"'
CXXFLAGS_LOCAL = $(ALL_CXXFLAGS) -Wall -I. -I$(LOCALAGENTDIR)
CXXFLAGS_LINK  = $(ALL_CXXFLAGS) -lcppunit -lstdc++

EXE = test_ninka

OBJECTS = test_ninkawrapper.o

$(EXE): run_tests.cc $(OBJECTS) libninka.a ${CXXFOLIB}
	$(CXX) run_tests.cc -o $@ $(OBJECTS) $(LOCALAGENTDIR)/libninka.a $(CXXFLAGS_LINK)

$(OBJECTS): %.o: %.cc
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF) $<

$(CXXFOLIB):
	$(MAKE) -C $(CXXFOLIBDIR)

libninka.a: $(OBJECTS)
	$(MAKE) -C $(LOCALAGENTDIR) $@

all: $(EXE)

test: all
	./$(EXE)

coverage:
	@echo "nothing to do"

clean:
	rm -f $(EXE) *.o *.a *.gcno *.gcda *Tests-Results.xml

.PHONY: all test coverage clean

include $(DEPS)
