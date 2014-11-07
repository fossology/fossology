# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP=../../..
VARS=$(TOP)/Makefile.conf

include $(VARS)

LOCAL_CFLAGS  = -std=c99 $(ALL_CFLAGS) -I. -Werror -Wall -Wextra -fopenmp
DEF           = -DDATADIR='"$(MODDIR)"'
CONFDIR       = $(DESTDIR)$(SYSCONFDIR)
LOCAL_LDFLAGS = $(FO_LDFLAGS) -lm -fopenmp -lfossology

EXE=monk
TESTDIR = ../agent_tests
OBJECTS  = monk.o string_operations.o file_operations.o database.o\
           license.o highlight.o match.o hash.o diff.o extended.o\
           _squareVisitor.o
COVERAGE = monk_cov.o string_operations_cov.o file_operations_cov.o\
           database_cov.o license_cov.o highlight_cov.o match_cov.o\
           hash_cov.o diff_cov.o extended_cov.o _squareVisitor_cov.o

$(EXE): $(FOLIB) $(VARS) $(OBJECTS)
	$(CC) $(OBJECTS) $(DEF) $(LOCAL_LDFLAGS) -o $@

all: _squareVisitor.h $(FOLIB) $(EXE)

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
	$(CC) -c $(LOCAL_CFLAGS) $(DEF) $<

string_operations.o: %.o: %.c %.h
	$(CC) -c $(LOCAL_CFLAGS) $<

file_operations.o: %.o: %.c %.h
	$(CC) -c $(LOCAL_CFLAGS) $<

license.o: %.o: %.c %.h
	$(CC) -c $(LOCAL_CFLAGS) $<

database.o: %.o: %.c %.h
	$(CC) -c $(LOCAL_CFLAGS) $<

highlight.o: %.o: %.c %.h
	$(CC) -c $(LOCAL_CFLAGS) $<

hash.o: %.o: %.c %.h
	$(CC) -c $(LOCAL_CFLAGS) $<

diff.o: %.o: %.c %.h
	$(CC) -c $(LOCAL_CFLAGS) $<

match.o: %.o: %.c %.h _squareVisitor.o
	$(CC) -c $(LOCAL_CFLAGS) $<

extended.o: %.o: %.c %.h
	$(CC) -c $(LOCAL_CFLAGS) $(DEFS) $<

_squareVisitor.o: _squareVisitor.c
	$(CC) -c $(LOCAL_CFLAGS) $<

_squareVisitor.c _squareVisitor.h: squareBuilder
	sh genSquareVisitor

squareBuilder: buildSquareVisitor.c
	$(CC) buildSquareVisitor.c $(DEF) $(LOCAL_CFLAGS) $(LOCAL_LDFLAGS) -lm -o $@

$(COVERAGE): %_cov.o: %.c
	$(CC) -c $< $(LOCAL_CFLAGS) $(FLAG_COV) $(DEF) $(DEFS) -o $@

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
