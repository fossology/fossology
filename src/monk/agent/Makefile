# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP=../../..
VARS=$(TOP)/Makefile.conf

include $(VARS)

CFLAGS_LOCAL = -std=c99 $(ALL_CFLAGS) -I. -Werror -Wall -Wextra -fopenmp
DEF          = -DDATADIR='"$(MODDIR)"'
CONFDIR      = $(DESTDIR)$(SYSCONFDIR)
CFLAGS_LINK  = $(ALL_CFLAGS) -lm -fopenmp

EXE=monk
TESTDIR = ../agent_tests
LIBDIR = $(TOP)/src/lib/c
OBJECTS  = monk.o string_operations.o file_operations.o database.o\
           license.o highlight.o match.o hash.o diff.o extended.o\
           _squareVisitor.o
COVERAGE = monk_cov.o string_operations_cov.o file_operations_cov.o\
           database_cov.o license_cov.o highlight_cov.o match_cov.o\
           hash_cov.o diff_cov.o extended_cov.o _squareVisitor_cov.o

$(EXE): $(FOLIB) $(VARS) $(OBJECTS)
	$(CC) $(OBJECTS) $(DEF) $(CFLAGS_LINK) -o $@

all: _squareVisitor.h fossologyLib $(EXE)

#######################
# library build rules #
#######################

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

libmonk.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)
	
libmonk_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

######################
# object build rules #
######################

monk.o: %.o: %.c %.h
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

string_operations.o: %.o: %.c %.h
	$(CC) -c $(CFLAGS_LOCAL) $<

file_operations.o: %.o: %.c %.h
	$(CC) -c $(CFLAGS_LOCAL) $<

license.o: %.o: %.c %.h
	$(CC) -c $(CFLAGS_LOCAL) $<

database.o: %.o: %.c %.h
	$(CC) -c $(CFLAGS_LOCAL) $<

highlight.o: %.o: %.c %.h
	$(CC) -c $(CFLAGS_LOCAL) $<

hash.o: %.o: %.c %.h
	$(CC) -c $(CFLAGS_LOCAL) $<

diff.o: %.o: %.c %.h
	$(CC) -c $(CFLAGS_LOCAL) $<

match.o: %.o: %.c %.h _squareVisitor.o
	$(CC) -c $(CFLAGS_LOCAL) $<

extended.o: %.o: %.c %.h
	$(CC) -c $(CFLAGS_LOCAL) $<

_squareVisitor.o: _squareVisitor.c
	$(CC) -c $(CFLAGS_LOCAL) $<

_squareVisitor.c _squareVisitor.h: squareBuilder
	sh genSquareVisitor

fossologyLib: 
	$(MAKE) -C $(LIBDIR)
	
squareBuilder: buildSquareVisitor.c
	$(CC) buildSquareVisitor.c $(DEF) $(CFLAGS_LOCAL) -lm -o $@

$(COVERAGE): %_cov.o: %.c 
	$(CC) -c $< $(CFLAGS_LOCAL) $(FLAG_COV) $(DEF) $(DEFS) -o $@

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
	rm -f $(EXE) *.o *.a *.gcno *.gcda core _squareVisitor.h _squareVisitor.c \
	squareBuilder _squareVisitor.h.gen

.PHONY: all install uninstall clean test
