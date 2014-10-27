# FOSSology Makefile - ununpack/agent_tests
# Copyright (C) 2009-2011 Hewlett-Packard Development Company, L.P.

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

AGENTDIR = ../../agent
CUNIT_LIB_DIR = $(TOP)/src/testing/lib/c
CUNIT_LIB = -L $(CUNIT_LIB_DIR) -l focunit -I $(CUNIT_LIB_DIR)
TEST_LIB_DIR = $(TOP)/src/testing/db/c
TEST_LIB = -L $(TEST_LIB_DIR) -l fodbreposysconf -I $(TEST_LIB_DIR)

CFLAGS_LOCAL = -lpq -lmagic $(ALL_CFLAGS) -I$(AGENTDIR) -I./ $(CUNIT_LIB) -lcunit $(TEST_LIB)
EXE = run_tests

OBJS =	run_tests.o \
		test_CopyFile.o \
		test_FindCmd.o \
		test_Prune.o \
		test_RunCommand.o \
		test_Traverse.o \
		test_ununpack-ar.o \
		test_TraverseChild.o \
		test_TraverseStart.o \
		test_ununpack-disk.o \
		test_ununpack-iso.o \
		test_TaintString.o \
		test_IsFunctions.o \
		test_ContainerInfo.o \
		test_Checksum.o \
		test_PathCheck.o \
		test_DBInsert.o
#		test_TraverseUnunpackEntry.o \

FOCUNIT = libfocunit.a

# list of the external test data files required by this test
# UNLIKE MOST OTHER TESTS, these will be found in ../
TEST_FILES = unpack-test-data.tar.bz2

all: $(EXE)

$(EXE): libununpack.a $(FOLIB) $(OBJS) $(VARS) $(FOCUNIT)
	$(CC) $(OBJS) $(AGENTDIR)/libununpack.a $(CFLAGS_LOCAL) -o $@

$(OBJS): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

# target to retrieve remote test data if it doesn't already exist
# make sure your /etc/wgetrc file is configured correctly!
testdata:
	@echo "Retrieve test files"
	@# for each test file specified, look for it in ../testdata
	@# if it exists, do nothing.
	@# if it does not exist, look in /var/www/testfiles
	@# if not in /var/www/testfiles, wget it from fossology.org
	@$(foreach test_file, $(TEST_FILES), \
		if [ -e ../$(test_file) ]; then \
			echo "../$(test_file) exists"; \
		elif [ -e /var/www/testfiles/$(test_file) ]; then \
			echo "$(test_file) found in /var/www/testfiles"; \
			cp /var/www/testfiles/$(test_file) ../; \
		else \
			echo "$(test_file) does not exist. need to wget from fossology.org"; \
			wget --directory-prefix=../ http://www.fossology.org/testdata/$(test_file); \
		fi; \
	)

$(FOCUNIT):
	$(MAKE) -C $(CUNIT_LIB_DIR) $@

test: all testdata
	cd ..; tar -xf unpack-test-data.tar.bz2
	./$(EXE)

coverage: $(OBJS) libununpack_cov.a $(VARS) $(FOLIB)
	$(CC) -o $(EXE) $(OBJS) $(AGENTDIR)/libununpack_cov.a $(FLAG_COV) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory $(AGENTDIR) --capture --output-file cov.txt; \
	genhtml -o results cov.txt

libununpack_cov.a:
	$(MAKE) -C $(AGENTDIR) $@

libununpack.a:
	$(MAKE) -C $(AGENTDIR) $@

clean:
	rm -rf $(EXE) *.o core *.xml *.txt results test-result ../unpack-test-data.tar.bz2 ../test-data

.PHONY: all test coverage clean libununpack_cov.a libununpack.a

include $(DEPS)
