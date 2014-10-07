# FOSSology Makefile - test for mimetype
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

LOCALAGENTDIR = ../../agent
CUNIT_LIB_DIR = $(TOP)/src/testing/lib/c
CUNIT_LIB = -L $(CUNIT_LIB_DIR) -l focunit -I $(CUNIT_LIB_DIR)
TEST_LIB_DIR = $(TOP)/src/testing/db/c
TEST_LIB = -L $(TEST_LIB_DIR) -l fodbreposysconf -I $(TEST_LIB_DIR)


CFLAGS_LOCAL = $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lmagic -lpq $(ALL_CFLAGS) -I$(LOCALAGENTDIR)/ -I./ $(CUNIT_LIB) -lcunit $(TEST_LIB)
EXE = test_mimetype
TEST_OBJ_RUN = testRun.o
TEST_OBJ = ./finder/testDBCheckMime.o \
           ./finder/testDBLoadMime.o \
           ./finder/testDBFindMime.o \
           ./finder/testOtheFunctions.o

all: $(EXE)

$(EXE): libmimetype.a $(TEST_OBJ_RUN) $(TEST_OBJ) $(DB) $(REPO) $(VARS) cunit_lib test_lib
	@echo "make mimetype agent unit test"
	$(CC) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/libmimetype.a $(CFLAGS_LOCAL) -o $@

$(TEST_OBJ_RUN): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@

$(TEST_OBJ): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@ -g

cunit_lib:
	$(MAKE) -C $(CUNIT_LIB_DIR)

test_lib:
	$(MAKE) -C $(TEST_LIB_DIR)

test: all $(EXE)
	@echo "execute mimetype test"
	./$(EXE)

coverage: libmimetype_cov.a $(TEST_OBJ_RUN) $(TEST_OBJ) $(DB) $(REPO) $(VARS) cunit_lib test_lib
	@echo "execute mimetype test and code coverage"
	$(CC) -o $(EXE) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/libmimetype_cov.a $(FLAG_COV) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR)/ --capture --output-file cov.txt; \
	genhtml -o results cov.txt

libmimetype_cov.a:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

libmimetype.a:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

clean:
	rm -fr $(EXE) $(TEST_OBJ) $(TEST_OBJ_RUN) core *.xml *.txt *.gc* results test_result

include $(DEPS)

.PHONY: all install uninstall clean test
