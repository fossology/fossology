# FOSSology Makefile - test for wget_agent
# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../../
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

LOCALAGENTDIR = ../../agent
CUNIT_LIB_DIR = $(TOP)/src/testing/lib/c
CUNIT_LIB = -L $(CUNIT_LIB_DIR) -l focunit
TEST_LIB_DIR = $(TOP)/src/testing/db/c
TEST_LIB = -L $(TEST_LIB_DIR) -l fodbreposysconf

CFLAGS_LOCAL = $(FO_CFLAGS) -I$(LOCALAGENTDIR)/ -I./ -I $(TEST_LIB_DIR) -I $(CUNIT_LIB_DIR) -DCU_VERSION_P=$(CUNIT_VERSION) $(shell libgcrypt-config --cflags)
LDFLAGS_LOCAL = $(FO_LDFLAGS) -lpq $(CUNIT_LIB) -lcunit $(TEST_LIB) $(shell libgcrypt-config --libs)

EXE = test_wget_agent
TEST_OBJ_RUN = testRun.o utility.o
TEST_OBJ = ./wget_agent/testGetURL.o \
           ./wget_agent/testSetEnv.o \
           ./wget_agent/testUtilities.o \
           ./wget_agent/testDBLoadGold.o
CHKHDR = $(LOCALAGENTDIR)/../../ununpack/agent/checksum.h
CHKSRC = $(CHKHDR:%.h=%.c)

all: $(EXE)

$(EXE): libwget_agent.a $(TEST_OBJ_RUN) $(TEST_OBJ) $(CHKHDR) $(CHKSRC) $(VARS) $(DB) $(REPO) $(AGENTLIB) $(FOLIB) cunit_lib test_lib
	$(CC) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/libwget_agent.a $(CHKSRC) $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL) -o $@

$(TEST_OBJ_RUN): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

$(TEST_OBJ): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@

cunit_lib:
	$(MAKE) -C $(CUNIT_LIB_DIR)

test_lib:
	$(MAKE) -C $(TEST_LIB_DIR)

test: all
	./$(EXE)

coverage: libwget_agent_cov.a $(TEST_OBJ_RUN) $(TEST_OBJ) $(CHKHDR) $(CHKSRC) $(VARS) $(DB) $(REPO) $(AGENTLIB) cunit_lib test_lib
	$(CC) -o $(EXE) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/libwget_agent_cov.a $(CHKSRC) $(FLAG_COV) $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR)/ --capture --output-file cov.txt; \
	genhtml -o results cov.txt

libwget_agent_cov.a:
	$(MAKE) -C $(LOCALAGENTDIR) $@

libwget_agent.a:
	$(MAKE) -C $(LOCALAGENTDIR) $@

clean:
	rm -rf $(EXE) $(TEST_OBJ) $(TEST_OBJ_RUN) core *.xml *.txt *.gc* results test_result

.PHONY: all test coverage clean

include $(DEPS)
