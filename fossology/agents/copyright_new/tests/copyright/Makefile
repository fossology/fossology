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

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) $(ALL_CFLAGS) -I../../
EXE = test_copyright

SOURCES = test_cvector.c \

OBJECTS = test_cvector.o \

TO_TEST = 

all: $(EXE)
$(EXE): $(OBJECTS) cvector.o
	$(CC) run_tests.c -o $@ $(OBJECTS) ../../cvector.o -lcunit

$(OBJECTS): %.o: %.c
	$(CC) -c -g -I../../ $<

test: all $(EXE)
	./$(EXE)

cvector.o:
	$(MAKE) -C ../../ $@

clean:
	rm -f $(EXE) *.o *.g *.xml *.txt results
