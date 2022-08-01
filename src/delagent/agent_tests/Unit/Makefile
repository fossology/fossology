#  SPDX-FileCopyrightText: © 2011-2014 Hewlett-Packard Development Company, L.P.

#  SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

LOCALAGENTDIR = ../../agent
CUNIT_LIB_DIR = $(TOP)/src/testing/lib/c
CUNIT_LIB = -L $(CUNIT_LIB_DIR) -l focunit -I $(CUNIT_LIB_DIR)
TEST_LIB_DIR = $(TOP)/src/testing/db/c
TEST_LIB = -L $(TEST_LIB_DIR) -l fodbreposysconf -I $(TEST_LIB_DIR)

LDFLAGS_LOCAL = $(FO_LDFLAGS) -lcunit $(CUNIT_LIB) $(TEST_LIB) $(shell libgcrypt-config --libs)
CFLAGS_LOCAL = $(FO_CFLAGS) -I$(LOCALAGENTDIR) -DCU_VERSION_P=$(CUNIT_VERSION) $(shell libgcrypt-config --cflags)

EXE = test_delagent
TEST_OBJ_RUN = testRun.o
TEST_OBJ = testListFolders.o testDeleteFolders.o

# these are the external test data files needed by the tests
# at the moment they will be found in the delagent/ directory, but this
# may change as we organize the test data in a more consistent way
TEST_FILES = testdb_all.gz testrepo_files.tar.gz testrepo_gold.tar.gz

all: $(EXE)

$(EXE): delagent.o $(TEST_OBJ_RUN) $(TEST_OBJ) $(VARS) cunit_lib test_lib $(FOLIB)
	$(CC) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/delagent.o $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL) -o $@

$(TEST_OBJ_RUN): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) -I $(CUNIT_LIB_DIR) -I $(TEST_LIB_DIR) $<

$(TEST_OBJ): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@

test: all $(TEST_FILES:%=../testdata/%)
	./$(EXE)

cunit_lib:
	$(MAKE) -C $(CUNIT_LIB_DIR)

test_lib:
	$(MAKE) -C $(TEST_LIB_DIR)

coverage: all $(TEST_FILES:%=../testdata/%) delagent_cov.o $(TEST_OBJ_RUN) $(TEST_OBJ) $(VARS)
	$(CC) -o $(EXE) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/delagent_cov.o $(FLAG_COV) $(LDFLAGS_LOCAL) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR)/ --capture --output-file cov.txt; \
	genhtml -o results cov.txt

delagent_cov.o:
	$(MAKE) -C $(LOCALAGENTDIR) $@

delagent.o:
	$(MAKE) -C $(LOCALAGENTDIR) $@

clean:
	rm -rf $(EXE) $(TEST_OBJ) $(TEST_OBJ_RUN) core *.xml *.txt *.gc* results test_result

.PHONY: all test coverage clean

include $(DEPS)
