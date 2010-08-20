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
$(EXE): $(TEST_OBJ)
	$(CC) -o $@ $(TEST_OBJ) -I/usr/local/include -L/usr/local/lib -lcunit ../../libpkgagent.so

$(TEST_OBJ): %.o: %.c
	$(CC) -c -g $<

test: all $(EXE)
	./$(EXE)

coverage: $(TEST_OBJ) libpkgagent_cov.so
	$(CC) -o $(EXE) $(TEST_OBJ) -I/usr/local/include -L/usr/local/lib -lcunit ../../libpkgagent_cov.so; \
	./$(EXE); \
	lcov --directory  ../../   --capture --output-file cov.txt; \
	genhtml  -o  results cov.txt

libpkgagent_cov.so:
	$(MAKE) -C ../../ $@

clean:
	rm -fr $(EXE) *.o core *.so *.g* *.xml *.txt results

