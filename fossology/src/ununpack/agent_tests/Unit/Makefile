# FOSSology Makefile - ununpack/agent_tests
# Copyright (C) 2009-2011 Hewlett-Packard Development Company, L.P.
TOP=../../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)
AGENTDIR=../../agent
TESTDIR=$(TOP)/src/testing/lib/c
TESTLIB= -L $(TESTDIR) -l focunit -I $(TESTDIR)
DATA=../unpack-test-data.tar.bz2

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

FOCUNIT = libfocunit.a

all: $(EXE)

$(EXE): libununpack.a $(FOLIB) $(OBJS) $(VARS) $(FOCUNIT)
	$(CC) $(OBJS) $(AGENTDIR)/libununpack.a $(CFLAGS_LOCAL) -o $@

$(OBJS): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $<

$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

$(DATA):
	@echo "Downloading ununpack data"
	rsync -acv fonightly.usa.hp.com:/var/www/testfiles/unpack-test-data.tar.bz2 ..	
	
$(FOCUNIT):
	$(MAKE) -C $(TESTDIR) $@
	
download: ../unpack-test-data.tar.bz2
	@echo "untaring ununpack data"
	cd ..; \
	tar -xf unpack-test-data.tar.bz2; \
	cd Unit
	
test: all download $(EXE)
	@echo "Make test ununpack"
	./$(EXE)

coverage: $(OBJS) libununpack_cov.a $(VARS) $(FOLIB)
	$(CC) -o $(EXE) $(OBJS) $(AGENTDIR)/libununpack_cov.a $(FLAG_COV) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory $(AGENTDIR) --capture --output-file cov.txt; \
	genhtml  -o results cov.txt

libununpack_cov.a:
	$(MAKE) -C $(AGENTDIR) $@

libununpack.a:
	$(MAKE) -C $(AGENTDIR) $@

clean:
	rm -fr $(EXE) *.o core *.xml *.txt results test-result unpack-test-data.tar.bz2 test-data

include $(DEPS)

.PHONY: all install uninstall clean test download libununpack_cov.a libununpack.a $(DATA)
