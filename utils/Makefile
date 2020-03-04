# Copyright Siemens AG 2019
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

CXXFLAGS_LOCAL = -I. -Wall -g -std=c++11
CXXFLAGS_LINK = -lboost_program_options -lstdc++ \
                $(shell pkg-config --libs icu-uc)

EXE = fo_unicode_clean

OBJECTS = FossologyUnicodeClean.o

all: $(EXE)

$(EXE): $(OBJECTS)
	$(CXX) $(OBJECTS) $(CXXFLAGS_LINK) -o $@

$(OBJECTS): %.o: %.cc %.hpp
	$(CXX) -c $(CXXFLAGS_LOCAL) $(DEF) $<

clean:
	rm -f $(EXE) *.o *.a *.gcno *.gcda core

.PHONY: all clean
