# SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

LOCALAGENTDIR = ../../agent

TESTDIR = $(TOP)/src/testing/lib/c
TESTLIB = -L$(TESTDIR) -l focunit -lgthread-2.0 -lgio-2.0 -lgobject-2.0
CFLAGS_LOCAL = $(FO_CFLAGS) -I. -I$(LOCALAGENTDIR) -I $(TESTDIR) -DCU_VERSION_P=$(CUNIT_VERSION)
LDFLAGS_LOCAL = $(FO_LDFLAGS) -lpcre -lcunit $(TESTLIB)
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
          testScheduler.o \
	  utils.o

all: $(EXE)

test: all fossology_testconfig
	./$(EXE)

coverage: testRun.c $(OBJECTS) $(COV) $(VARS) $(FOLIB) fossology_testconfig $(EXE)
	$(CC) $< -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/$(COV) $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL) $(FLAG_COV); \
	./$(EXE)
	$(call coverage-report-html,$(LOCALAGENTDIR))

$(EXE): testRun.c $(OBJECTS) $(LIB) $(FOCUNIT) $(FOLIB)
	$(CC) $< -o $(EXE) $(OBJECTS) $(LOCALAGENTDIR)/$(LIB) $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL)

$(LIB):
	$(MAKE) -C $(LOCALAGENTDIR) $@

$(COV):
	$(MAKE) -C $(LOCALAGENTDIR) $@

$(FOCUNIT):
	$(MAKE) -C $(TESTDIR) $@

$(OBJECTS): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

clean:
	rm -rf $(EXE) *.a *.o *.g *.xml *.txt *.gcda *.gcno *.log results

.PHONY: all test coverage clean

include $(DEPS)
