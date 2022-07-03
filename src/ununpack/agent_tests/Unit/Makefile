# FOSSology Makefile - ununpack/agent_tests
# SPDX-FileCopyrightText: © 2009-2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

AGENTDIR = ../../agent
CUNIT_LIB_DIR = $(TOP)/src/testing/lib/c
CUNIT_LIB = -L $(CUNIT_LIB_DIR) -l focunit
TEST_LIB_DIR = $(TOP)/src/testing/db/c
TEST_LIB = -L $(TEST_LIB_DIR) -l fodbreposysconf 

CFLAGS_LOCAL = $(FO_CFLAGS) -I$(AGENTDIR) -I./ -I $(TEST_LIB_DIR) -I $(CUNIT_LIB_DIR) -DCU_VERSION_P=$(CUNIT_VERSION) $(shell libgcrypt-config --cflags)
LDFLAGS_LOCAL = -lmagic $(FO_LDFLAGS) $(CUNIT_LIB) -lcunit $(TEST_LIB) $(shell libgcrypt-config --libs)
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
# UNLIKE MOST OTHER TESTS, these will be found in ../testdata
TEST_FILES = circularlinks.tar null_file test1.zip.tar.gz test.cab test.cpio.gz testdir.tar.gz test.iso test.jar test.pdf
TEST_FILES += fossI16L335U29.tar.bz2 test.tar.Z testtwo.zip test.zip emptydirs.tar test.ar test.cpio test.deb
TEST_FILES += test_1-1.dsc test.msi test.rar test.rpm test_1.orig.tar.gz testthree.zip test.z fossI16L518.7z
TEST_FILES += libununpack.a test.exe test_1-1.debian.tar.xz ext2file.fs ext3file.fs fatfile.fs ntfsfile.fs

../testdata/libununpack.a: libununpack.a
	cp $(AGENTDIR)/libununpack.a ../testdata/libununpack.a

../testdata/fossI16L335U29.tar.bz2:
	cp ../../../testing/dataFiles/TestData/archives/fossI16L335U29.tar.bz2 ../testdata/fossI16L335U29.tar.bz2

../testdata/fossI16L518.7z:
	cp ../../../testing/dataFiles/TestData/archives/fossI16L518.7z ../testdata/fossI16L518.7z

all: $(EXE)

$(EXE): libununpack.a $(FOLIB) $(OBJS) $(VARS) $(FOCUNIT) libfodbreposysconf.a
	$(CC) $(OBJS) $(AGENTDIR)/libununpack.a $(LDFLAGS_LOCAL) $(CFLAGS_LOCAL) -o $@

$(OBJS): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

# target to get test data and test ununpack on them
testdata:
	@echo "Unpacking test files"
	@cd ../testdata && tar -xvjf testdata.tar.bz2

$(FOCUNIT):
	$(MAKE) -C $(CUNIT_LIB_DIR) $@

test: all testdata setupDB testUnunpack dropDB
	./$(EXE)

coverage: testdata $(OBJS) libununpack_cov.a $(VARS) $(FOLIB)
	$(CC) -o $(EXE) $(OBJS) $(AGENTDIR)/libununpack_cov.a $(FLAG_COV) $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL); \
	./$(EXE)

libununpack_cov.a:
	$(MAKE) -C $(AGENTDIR) $@

libununpack.a:
	$(MAKE) -C $(AGENTDIR) $@

libfodbreposysconf.a:
	$(MAKE) -C $(TEST_LIB_DIR)

setupDB: ../testdata/setupDb.sh
	../testdata/setupDb.sh

dropDB: ../testdata/dropDb.sh
	../testdata/dropDb.sh

testUnunpack: $(TEST_FILES:%=../testdata/%)
	$(foreach test_file, $(TEST_FILES), ../testdata/testit.sh ../testdata/$(test_file) ;)

clean:
	rm -rf $(EXE) *.o core *.xml *.txt results test-result *.dir *.unpacked
	rm -rf $(TEST_FILES:%=../testdata/%) $(TEST_FILES)
	rm -rf ../testdata/*.dir ../testdata/*.xml ../testdata/*.meta

.PHONY: all test coverage clean libununpack_cov.a libununpack.a

include $(DEPS)
