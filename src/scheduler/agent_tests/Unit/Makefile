######################################################################
# Copyright (C) 2011-2012 Hewlett-Packard Development Company, L.P.
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

LOCALAGENTDIR = ../../agent

TESTDIR = $(TOP)/src/testing/lib/c
TESTLIB = -L$(TESTDIR) -l focunit -I $(TESTDIR) -lgthread-2.0 -lgio-2.0
CFLAGS_LOCAL = $(ALL_CFLAGS) -I. -I$(LOCALAGENTDIR) -lpcre -lcunit $(TESTLIB)
DEF = -DLOG_DIR='"$(LOGDIR)"' \
      -DDEFAULT_SETUP='"$(SYSCONFDIR)"' \
      -DCLI_DIR='"$(DESTDIR)$(BINDIR)"' \
      -DFOSSDB_CONF='"$(SYSCONFDIR)/$(PROJECT)/Db.conf"' \
      -DPROJECT_USER='"$(PROJECTUSER)"' \
      -DPROJECT_GROUP='"$(PROJECTGROUP)"' \
      -DPROCESS_NAME='"$(EXE)"'

LIB = libscheduler.a
EXE = test_scheduler
COV = libscheduler_cov.a
FOCUNIT = libfocunit.a

OBJECTS = testHost.o \
          testInterface.o \
          testAgent.o \
          testEvent.o \
          testDatabase.o \
          testJob.o \
          testScheduler.o

all: $(EXE)

test: fossology_testconfig $(EXE)
	./$(EXE)

$(EXE): testRun.c $(OBJECTS) $(LIB) $(FOCUNIT)
	$(CC) $< -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/$(LIB) $(CFLAGS_LOCAL)

coverage: testRun.c $(OBJECTS) $(COV) $(VARS) $(FOLIB) fossology_testconfig $(EXE)
	@echo "make scheduler test coverage"
	$(CC) $< -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/$(COV) $(CFLAGS_LOCAL) $(FLAG_COV); \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR) --capture --output-file cov.txt; \
	genhtml -o results cov.txt

$(LIB):
	$(MAKE) -C $(LOCALAGENTDIR) $@

$(COV):
	$(MAKE) -C $(LOCALAGENTDIR) $@

$(FOCUNIT):
	$(MAKE) -C $(TESTDIR) $@

######################
# object build rules #
######################

$(OBJECTS): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

clean:
	@echo "make copyright agent_tests clean"
	rm -rf $(EXE) *.a *.o *.g *.xml *.txt *.gcda *.gcno *.log results

install:
	@echo "make copyright agent_tests nothing to install"

uninstall:
	@echo "make copyright agent_tests nothing to uninstall"

include $(DEPS)

.PHONY: all install uninstall clean test
