# FOSSology Makefile - test for agents/pkgagent
# Copyright (C) 2009-2011 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
LOCALAGENTDIR=../agent

CFLAGS_LOCAL=-I/usr/include/rpm -lpq -lrpm $(ALL_CFLAGS) -I$(LOCALAGENTDIR) -lcunit
EXE=test_pkgagent
CFLAGS_LOCAL_RPM_4_4=$(CFLAGS_LOCAL) -D_RPM_4_4
CFLAGS_LOCAL_RPM=$(CFLAGS_LOCAL) -D_RPM_4_4_COMPAT
TEST_OBJ=testRun.o testTrim.o testGetFieldValue.o testRecordMetadataRPM.o testRecordMetadataDEB.o testGetMetadataDebSource.o testGetMetadataDebBinary.o 
all: $(EXE)
$(EXE): $(TEST_OBJ) testGetMetadata.o pkgagent.o $(VARS)
	$(CC) -o $@ $(LOCALAGENTDIR)/pkgagent.o $(TEST_OBJ) testGetMetadata.o $(CFLAGS_LOCAL)

$(TEST_OBJ): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

testGetMetadata.o:testGetMetadata.c
	if expr `rpm --version|awk '{print $$3}'|awk -F. '{print $$1"."$$2}'` \>= 4.5 >/dev/null ; then \
		$(CC) -c $(CFLAGS_LOCAL_RPM) $<; \
	else \
		$(CC) -c $(CFLAGS_LOCAL_RPM_4_4) $<; \
	fi

test: all $(EXE)
	./$(EXE)

coverage: $(TEST_OBJ) testGetMetadata.o pkgagent_cov.o
	$(CC) -o $(EXE) $(LOCALAGENTDIR)/pkgagent_cov.o $(TEST_OBJ) testGetMetadata.o $(FLAG_COV) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory  $(LOCALAGENTDIR)/   --capture --output-file cov.txt; \
	genhtml  -o  results cov.txt

pkgagent_cov.o:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

pkgagent.o:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

clean:
	rm -fr $(EXE) *.o core *.xml *.txt results

