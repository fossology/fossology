# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)

CFLAGS_LOCAL = -std=c99 $(ALL_CFLAGS) -I. `pkg-config json --cflags`
DEF          = -DDATADIR='"$(MODDIR)"'
CONFDIR      = $(DESTDIR)$(SYSCONFDIR)
CFLAGS_LINK  = $(ALL_CFLAGS) -lm -fopenmp -lmxml -L ./libmxml.a `pkg-config json --cflags --libs`

EXE = reportgen

OBJECTS  = utils.o main.o list.o
COVERAGE = 

TESTDIR = ../agent_tests

$(EXE): $(FOLIB) $(VARS) $(OBJECTS)
	$(CC) $(OBJECTS) $(DEF) $(CFLAGS_LINK) -o $@

all: $(FOLIB) $(EXE)

#######################
# library build rules #
#######################

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

$(OBJECTS): %.o: %.c %.h
	$(CC) -c $< $(CFLAGS) $(DEFS) $(CFLAGS_LOCAL)

install: all
	@echo "make reportgen agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(MODDIR)/$(EXE)/agent/$(EXE)


uninstall:
	@echo "make reportgen agent uninstall"
	rm -rf $(DESTDIR)$(MODDIR)/$(EXE)/agent


clean:
	rm -f reportgen *.o *.a

include $(DEPS)

.PHONY: all install uninstall clean test
