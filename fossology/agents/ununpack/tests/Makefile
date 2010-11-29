# FOSSology Makefile - test for agents/ununpack
# Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq -lmagic $(ALL_CFLAGS) -I../ -I./ -lcunit
EXE=test_ununpack
TEST_OBJ_RUN=testRun.o utility.o 
TEST_OBJ_UNUNPACK=./ununpack/testUnunpackEntry.o \
                  ./ununpack/testTraverseStart.o \
                  ./ununpack/testFindCmd.o \
                  ./ununpack/testTraverse.o \
                  ./ununpack/testRunCommand.o \
                  ./ununpack/testTraverseChild.o \
                  ./ununpack/testPrune.o \
                  ./ununpack/testCopyFile.o
TEST_OBJ_UNUNPACK_AR=./ununpack-ar/testExtractAR.o
TEST_OBJ_UNUNPACK_ISO=./ununpack-iso/testExtractISO.o
TEST_OBJ_UNUNPACK_DISK=./ununpack-disk/testExtractDisk.o \
                       ./ununpack-disk/testFatDiskName.o
TEST_OBJ=$(TEST_OBJ_RUN) $(TEST_OBJ_UNUNPACK) $(TEST_OBJ_UNUNPACK_AR) $(TEST_OBJ_UNUNPACK_ISO) $(TEST_OBJ_UNUNPACK_DISK)

all: $(EXE)

$(EXE): libununpack.a $(TEST_OBJ) $(VARS) $(DB) $(REPO) $(AGENTLIB)
	$(CC) $(TEST_OBJ) ../libununpack.a $(CFLAGS_LOCAL) -o $@

$(TEST_OBJ_RUN): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $<

$(TEST_OBJ_UNUNPACK): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@

$(TEST_OBJ_UNUNPACK_AR): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@ -g

$(TEST_OBJ_UNUNPACK_ISO): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@

$(TEST_OBJ_UNUNPACK_DISK): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@

test: all $(EXE)
	./$(EXE)

coverage: $(TEST_OBJ) libununpack_cov.a $(VARS) $(DB) $(REPO) $(AGENTLIB)
	$(CC) -o $(EXE) $(TEST_OBJ) ../libununpack_cov.a $(FLAG_COV) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory ../   --capture --output-file cov.txt; \
	genhtml  -o results cov.txt

libununpack_cov.a:
	$(MAKE) -C ../ $@

libununpack.a:
	$(MAKE) -C ../ $@

clean:
	rm -fr $(EXE) $(TEST_OBJ) core *.xml *.txt results test-result 

include $(DEPS)
