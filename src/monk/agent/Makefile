# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

CFLAGS_LOCAL = -std=c99 $(ALL_CFLAGS) -I. -Werror -Wall -Wextra -fopenmp
CONFDIR = $(DESTDIR)$(SYSCONFDIR)
CFLAGS_LINK = $(ALL_CFLAGS) -lm -fopenmp

EXE = monk monkbulk
OBJECTS = string_operations.o file_operations.o database.o \
          license.o highlight.o match.o hash.o diff.o common.o \
          cli.o scheduler.o \
          _squareVisitor.o
COVERAGE = string_operations_cov.o file_operations_cov.o \
           database_cov.o license_cov.o highlight_cov.o match_cov.o \
           hash_cov.o diff_cov.o common_cov.o \
           cli_cov.o scheduler_cov.o \
           _squareVisitor_cov.o

all: _squareVisitor.h $(EXE)

all_cov: _squareVisitor.h $(EXE:%=%_cov)

$(EXE): % : %.c $(FOLIB) $(VARS) $(OBJECTS)
	$(CC) -c $< $(CFLAGS_LOCAL) $(DEF) -o $@.o
	$(CC) $@.o $(OBJECTS) $(DEF) $(CFLAGS_LINK) -o $@

$(EXE:%=%_cov): %_cov : %.c $(FOLIB) $(VARS) $(COVERAGE)
	$(CC) -c $< $(CFLAGS_LOCAL) $(FLAG_COV) $(DEF) $(DEFS) -o $@.o
	$(CC) $@.o $(COVERAGE) $(FLAG_COV) $(DEF) $(CFLAGS_LINK) -o $@

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

_squareVisitor.c _squareVisitor.h: squareBuilder
	sh genSquareVisitor

squareBuilder: buildSquareVisitor.c
	$(CC) buildSquareVisitor.c $(DEF) $(CFLAGS_LOCAL) -lm -o $@

$(OBJECTS): %.o: %.c _squareVisitor.c
	$(CC) -c $< $(CFLAGS_LOCAL) -o $@

$(COVERAGE): %_cov.o: %.c _squareVisitor.c
	$(CC) -c $< $(CFLAGS_LOCAL) $(FLAG_COV) -o $@

#######################
# install build rules #
#######################

install: all $(EXE:%=install_%)

$(EXE:%=install_%): install_% : %
	$(INSTALL_PROGRAM) $< $(DESTDIR)$(MODDIR)/$</agent/$<

uninstall: $(EXE:%=uninstall_%)

$(EXE:%=uninstall_%): uninstall_% :
	rm -rf $(DESTDIR)$(MODDIR)/$</agent

clean:
	rm -f $(EXE) $(EXE:%=%_cov) *.o *.a *.gcno *.gcda core _squareVisitor.h _squareVisitor.c \
	squareBuilder _squareVisitor.h.gen

.PHONY: all install uninstall clean
