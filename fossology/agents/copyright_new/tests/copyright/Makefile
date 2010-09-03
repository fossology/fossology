######################################################################
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
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
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) $(ALL_CFLAGS) -I../../ -lpcre -lcunit
EXE = test_copyright

OBJECTS = test_cvector.o \
          test_copyright.o \
          test_radixtree.o \

TO_TEST = ../../copyright.c  ../../copyright.h \
          ../../cvector.c    ../../cvector.h \
          ../../radixtree.c  ../../radixtree.h \
          ../../pair.c       ../../pair.h \

all: $(EXE)

test: $(EXE)
	./$(EXE)

coverage: $(OBJECTS) libcopyright_cov.so run_tests.c
	$(CC) run_tests.c -o $(EXE) $(OBJECTS) ../../libcopyright_cov.so $(CFLAGS_LOCAL)
	./$(EXE)
	lcov --directory  ../../   --capture --output-file cov.txt; \
	genhtml  -o  results cov.txt

$(EXE): $(OBJECTS) libcopyright.so run_tests.c
	$(CC) run_tests.c -o $@ $(OBJECTS) ../../libcopyright.so $(CFLAGS_LOCAL)

$(OBJECTS): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

libcopyright.so: $(TO_TEST)
	$(MAKE) -C ../../ $@

libcopyright_cov.so: $(TO_TEST)
	$(MAKE) -C ../../ $@

clean:
	rm -f $(EXE) *.o *.g *.xml *.txt results
