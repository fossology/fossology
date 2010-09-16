# FOSSology Makefile - test for agents/pkgagent
# Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
TOP=../../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -I/usr/include/rpm -lpq -lrpm $(ALL_CFLAGS)
EXE=test_pkgagent
COVERAGE_FLAG= -fprofile-arcs -ftest-coverage
CFLAGS_LOCAL_RPM_4_4=$(CFLAGS_LOCAL) -D_RPM_4_4
CFLAGS_LOCAL_RPM=$(CFLAGS_LOCAL) -D_RPM_4_4_COMPAT
TEST_OBJ=testRun.o testGetFieldValue.o testGetMetadata.o 
all: $(EXE)
$(EXE): $(TEST_OBJ) pkgagent.o $(DB) $(REPO) $(AGENTLIB) $(VARS) $(HDRS)
	$(CC) -o $@ ../../pkgagent.o $(TEST_OBJ) $(CFLAGS_LOCAL) -I/usr/local/include -L/usr/local/lib -lcunit

$(TEST_OBJ): %.o: %.c
	$(CC) -c -g $<

test: all $(EXE)
	./$(EXE)

coverage: $(TEST_OBJ) pkgagent_cov.o
	$(CC) -o $(EXE) ../../pkgagent_cov.o $(TEST_OBJ) $(FLAG_COV) $(CFLAGS_LOCAL) -I/usr/local/include -L/usr/local/lib -lcunit; \
	./$(EXE); \
	lcov --directory  ../../   --capture --output-file cov.txt; \
	genhtml  -o  results cov.txt

pkgagent_cov.o:
	$(MAKE) -C ../../ $@

pkgagent.o:
	$(MAKE) -C ../../ $@

clean:
	rm -fr $(EXE) *.o core *.xml *.txt results

