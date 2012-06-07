######################################################################
# Copyright (C) 2010-2011 Hewlett-Packard Development Company, L.P.
# 
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
######################################################################
TOP = ../../../..
include $(TOP)/Makefile.conf
LOCALAGENTDIR=../../agent

TESTDIR = $(TOP)/src/testing/lib/c
TESTLIB = -L$(TESTDIR) -l focunit -I $(TESTDIR)
CFLAGS_LOCAL= $(ALL_CFLAGS) -I$(LOCALAGENTDIR) -lpcre -lcunit $(TESTLIB)
DEF = -DDATADIR='"$(DATADIR)"'
EXE = test_copyright

OBJECTS = test_cvector.o \
          test_copyright.o \
          test_radixtree.o \

COVERAGE = copyright_cov.o \
           cvector_cov.o \
           radixtree_cov.o \
           pair_cov.o \

all: $(EXE)
	@echo "make copyright Unit agent_tests"

test: $(EXE)
	@echo "make copyright Unit agent_tests"
	./$(EXE)

coverage: $(OBJECTS) libcopyright_cov.a run_tests.c ${FOLIB}
	@echo "make copyright agent_tests coverage"
	${MAKE} -C ${TESTDIR}
	$(CC) run_tests.c -o $(EXE) $(OBJECTS) libcopyright_cov.a $(CFLAGS_LOCAL) $(FLAG_COV)
	./$(EXE)
	lcov --directory . --capture --output-file cov.txt; \
	genhtml  -o  results cov.txt

$(EXE): $(OBJECTS) libcopyright.a run_tests.c ${FOLIB}
	${MAKE} -C ${TESTDIR}
	$(CC) run_tests.c -o $@ $(OBJECTS) $(LOCALAGENTDIR)/libcopyright.a $(CFLAGS_LOCAL)

$(OBJECTS): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

libcopyright.a:
	$(MAKE) -C $(LOCALAGENTDIR) $@

libcopyright_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

########################
# coverage build rules #
########################
copyright_cov.o: $(LOCALAGENTDIR)/copyright.c $(LOCALAGENTDIR)/copyright.h $(LOCALAGENTDIR)/cvector.h $(LOCALAGENTDIR)/radixtree.h
	$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL) $(DEF) -o $@

cvector_cov.o: $(LOCALAGENTDIR)/cvector.c $(LOCALAGENTDIR)/cvector.h
	$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL) -o $@

radixtree_cov.o: $(LOCALAGENTDIR)/radixtree.c $(LOCALAGENTDIR)/radixtree.h
	$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL) -o $@

pair_cov.o:  $(LOCALAGENTDIR)/pair.c $(LOCALAGENTDIR)/pair.h $(LOCALAGENTDIR)/cvector.h
	$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL) -o $@

clean:
	@echo "make copyright agent_tests clean"
	rm -rf $(EXE) *.a *.o *.g *.xml *.txt *.gcda *.gcno results

install:
	@echo "make copyright agent_tests nothing to install"
uninstall:
	@echo "make copyright agent_tests nothing to uninstall"

.PHONY: all install uninstall clean test 
include ${TOP}/Makefile.deps
