# FOSSology Makefile - test for agents/pkgagent
# Copyright (C) 2009-2013 Hewlett-Packard Development Company, L.P.
# Copyright (C) 2018 Siemens AG

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LOCALAGENTDIR = ../../agent
CUNIT_LIB_DIR = $(TOP)/src/testing/lib/c
CUNIT_LIB = -L $(CUNIT_LIB_DIR) -l focunit
TEST_LIB_DIR = $(TOP)/src/testing/db/c
TEST_LIB = -L $(TEST_LIB_DIR) -l fodbreposysconf

CFLAGS_LOCAL = -I/usr/include/rpm $(FO_CFLAGS) -I$(LOCALAGENTDIR) -I $(TEST_LIB_DIR) -I $(CUNIT_LIB_DIR) -DCU_VERSION_P=$(CUNIT_VERSION)
LDFLAGS_LOCAL = -lrpm $(FO_LDFLAGS) -lcunit $(CUNIT_LIB) $(TEST_LIB) -lrpmio
EXE = test_pkgagent
CFLAGS_LOCAL_RPM_4_4 = $(CFLAGS_LOCAL) -D_RPM_4_4
CFLAGS_LOCAL_RPM = $(CFLAGS_LOCAL) -D_RPM_4_4_COMPAT
TEST_OBJ = testRun.o testGetFieldValue.o testRecordMetadataRPM.o testRecordMetadataDEB.o testGetMetadataDebSource.o testGetMetadataDebBinary.o

all: $(EXE)

test: all
	./$(EXE)

coverage: $(TEST_OBJ) testGetMetadata.o pkgagent_cov.o cunit_lib test_lib
	$(CC) -o $(EXE) $(LOCALAGENTDIR)/pkgagent_cov.o $(TEST_OBJ) testGetMetadata.o $(FLAG_COV) $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL) ; \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR)/ --capture --output-file cov.txt; \
	genhtml -o results cov.txt

$(EXE): $(TEST_OBJ) testGetMetadata.o pkgagent.o $(VARS) cunit_lib test_lib
	$(CC) -o $@ $(LOCALAGENTDIR)/pkgagent.o $(TEST_OBJ) testGetMetadata.o $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL)

$(TEST_OBJ): %.o: %.c
	$(CC) -c $(CFLAGS_LOCAL) $<

testGetMetadata.o: testGetMetadata.c
	if expr `rpm --version|awk '{print $$3}'|awk -F. '{print $$1"."$$2}'` \>= 4.5 >/dev/null; then \
		$(CC) -c $< $(CFLAGS_LOCAL_RPM); \
	else \
		if expr `rpm --version|awk '{print $$3}'|awk -F. '{print $$1"."$$2}'` \>= 4.10 >/dev/null; then \
			$(CC) -c $< $(CFLAGS_LOCAL_RPM); \
		else \
			$(CC) -c $< $(CFLAGS_LOCAL_RPM_4_4); \
		fi \
	fi

cunit_lib:
	$(MAKE) -C $(CUNIT_LIB_DIR)

test_lib:
	$(MAKE) -C $(TEST_LIB_DIR)

pkgagent_cov.o:
	$(MAKE) -C $(LOCALAGENTDIR) $@

pkgagent.o:
	$(MAKE) -C $(LOCALAGENTDIR) $@

clean:
	rm -rf $(EXE) *.o core *.xml *.txt results

.PHONY: all test coverage clean
