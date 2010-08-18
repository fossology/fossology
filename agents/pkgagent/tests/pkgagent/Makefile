# FOSSology Makefile - test for agents/pkgagent
# Copyright (C) 2009 Hewlett-Packard Development Company, L.P.
TOP=../../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -I/usr/include/rpm -lpq -lrpm $(ALL_CFLAGS)
EXE=test_pkgagent
CFLAGS_LOCAL_RPM_4_4=$(CFLAGS_LOCAL) -D_RPM_4_4
CFLAGS_LOCAL_RPM=$(CFLAGS_LOCAL) -D_RPM_4_4_COMPAT
TEST_OBJ=testRun.o testGetFieldValue.o testGetMetadata.o 
all: $(EXE)
$(EXE): libpkgagent.so $(TEST_OBJ)
	$(CC) -o $@ $(TEST_OBJ) -I/usr/local/include -L/usr/local/lib -lcunit ./libpkgagent.so

libpkgagent.so: ../../pkgagent.c $(DB) $(REPO) $(AGENTLIB) $(VARS)
	if [ `rpm --version|awk '{print $$3}'` \< "4.5" ] ; then \
	$(CC) -shared -fPIC -fprofile-arcs -ftest-coverage $< $(CFLAGS_LOCAL_RPM_4_4) -o $@; \
	else \
	$(CC) -shared -fPIC -fprofile-arcs -ftest-coverage $< $(CFLAGS_LOCAL_RPM) -o $@; \
	fi

$(TEST_OBJ): %.o: %.c
	$(CC) -c -g $<

test: all $(EXE)
	./$(EXE)

coverage: test
	lcov --directory  .   --capture --output-file cov.txt; \
	genhtml  -o  results cov.txt

clean:
	rm -fr $(EXE) *.o core *.so *.g* *.xml *.txt results

