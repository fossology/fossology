# FOSSology Makefile - ununpack/agent_tests
# Copyright (C) 2009-2011 Hewlett-Packard Development Company, L.P.
TOP=../../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)
AGENTDIR=../../agent
TESTDIR=$(TOP)/src/testing/lib/c
TESTLIB= -L $(TESTDIR) -l focunit -I $(TESTDIR)

CFLAGS_LOCAL=-lpq -lmagic $(ALL_CFLAGS) -I$(AGENTDIR) -I./ -lcunit $(TESTLIB)
EXE=run_tests
OBJS=	run_tests.o \
		test_CopyFile.o \
		test_FindCmd.o \
		test_Prune.o \
		test_RunCommand.o \
		test_Traverse.o \
		test_ununpack-ar.o \
		test_TraverseChild.o \
		test_TraverseStart.o \
		test_ununpack-disk.o \
		test_ununpack-iso.o
#		test_TraverseUnunpackEntry.o \

all: $(EXE)

$(EXE): libununpack.a $(FOLIB) $(OBJS) $(VARS)
	$(CC) $(OBJS) $(AGENTDIR)/libununpack.a $(CFLAGS_LOCAL) -o $@

$(OBJS): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $<

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

test: all $(EXE)
        rsync -au fonightly.usa.hp.com:/var/www/testfiles/unpack-test-data.tar.bz2 ..; \
	cd ..; \
	tar -xf unpack-test-data.tar.bz2; \
	cd Unit; \
	./$(EXE)

coverage: $(OBJS) libununpack_cov.a $(VARS) $(FOLIB)
	$(CC) -o $(EXE) $(OBJS) $(AGENTDIR)/libununpack_cov.a $(FLAG_COV) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory ../   --capture --output-file cov.txt; \
	genhtml  -o results cov.txt

libununpack_cov.a:
	$(MAKE) -C $(AGENTDIR) $@

libununpack.a:
	$(MAKE) -C $(AGENTDIR) $@

clean:
	rm -fr $(EXE) *.o core *.xml *.txt results test-result unpack-test-data.tar.bz2 test-data

include $(DEPS)
