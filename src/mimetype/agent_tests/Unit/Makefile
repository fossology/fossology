# FOSSology Makefile - test for mimetype
# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

LOCALAGENTDIR = ../../agent
CUNIT_LIB_DIR = $(TOP)/src/testing/lib/c
CUNIT_LIB = -L $(CUNIT_LIB_DIR) -l focunit -I $(CUNIT_LIB_DIR)
TEST_LIB_DIR = $(TOP)/src/testing/db/c
TEST_LIB = -L $(TEST_LIB_DIR) -l fodbreposysconf -I $(TEST_LIB_DIR)

LDFLAGS_LOCAL = -lmagic $(FO_LDFLAGS) $(CUNIT_LIB) -lcunit $(TEST_LIB)
CFLAGS_LOCAL = $(FO_CFLAGS) -I$(LOCALAGENTDIR)/ -I./ -DCU_VERSION_P=$(CUNIT_VERSION)
EXE = test_mimetype
TEST_OBJ_RUN = testRun.o
TEST_OBJ = ./finder/testDBCheckMime.o \
           ./finder/testDBLoadMime.o \
           ./finder/testDBFindMime.o \
           ./finder/testOtheFunctions.o

all: $(EXE)

$(EXE): libmimetype.a $(TEST_OBJ_RUN) $(TEST_OBJ) $(DB) $(REPO) $(VARS) $(FOLIB) cunit_lib test_lib
	$(CC) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/libmimetype.a $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL) -o $@

$(TEST_OBJ_RUN): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) -I $(CUNIT_LIB_DIR) -I $(TEST_LIB_DIR) $< -o $@

$(TEST_OBJ): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@ -g

cunit_lib:
	$(MAKE) -C $(CUNIT_LIB_DIR)

test_lib:
	$(MAKE) -C $(TEST_LIB_DIR)

test: all
	./$(EXE)

coverage: libmimetype_cov.a $(TEST_OBJ_RUN) $(TEST_OBJ) $(DB) $(REPO) $(VARS) cunit_lib test_lib
	$(CC) -o $(EXE) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/libmimetype_cov.a $(FLAG_COV) $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR)/ --capture --output-file cov.txt; \
	genhtml -o results cov.txt

libmimetype_cov.a:
	$(MAKE) -C $(LOCALAGENTDIR) $@

libmimetype.a:
	$(MAKE) -C $(LOCALAGENTDIR) $@

clean:
	rm -rf $(EXE) $(TEST_OBJ) $(TEST_OBJ_RUN) core *.xml *.txt *.gc* results test_result

.PHONY: all test coverage clean

include $(DEPS)
