# FOSSology Makefile - test for agents/ununpack
# Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
TOP=../../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq -lmagic $(ALL_CFLAGS) -I../../ -I../ -lcunit
EXE=test_ununpack

TEST_OBJ=testRun.o testUnunpackEntry.o testTraverseStart.o testFindCmd.o testTraverse.o testRunCommand.o \
         testTraverseChild.o testPrune.o testCopyFile.o

all: $(EXE)

$(EXE): libununpack.a $(TEST_OBJ) utility.o
	$(CC) $(TEST_OBJ) ../utility.o ../../libununpack.a $(CFLAGS_LOCAL) -o $@

$(TEST_OBJ): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $< -g

utility.o:  
	$(MAKE) -C ../ $@

test: all $(EXE)
	./$(EXE)

coverage: $(TEST_OBJ) libununpack_cov.a  utility.o
	$(CC) -o $(EXE) $(TEST_OBJ) ../utility.o ../../libununpack_cov.a $(FLAG_COV) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory  ../../   --capture --output-file cov.txt; \
	genhtml  -o  results cov.txt

libununpack_cov.a:
	$(MAKE) -C ../../ $@

libununpack.a:
	$(MAKE) -C ../../ $@

clean:
	rm -fr $(EXE) *.o core *.xml *.txt results ../utility.o

