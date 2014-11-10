# FOSSology Makefile - test for agents/pkgagent
# Copyright (C) 2009-2014 Hewlett-Packard Development Company, L.P.
TOP=../../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
LOCALAGENTDIR=../../agent
CUNIT_LIB_DIR=$(TOP)/src/testing/lib/c
CUNIT_LIB= -L $(CUNIT_LIB_DIR) -l focunit -I $(CUNIT_LIB_DIR)
TEST_LIB_DIR=$(TOP)/src/testing/db/c
TEST_LIB= -L $(TEST_LIB_DIR) -l fodbreposysconf -I $(TEST_LIB_DIR)

LOCAL_CFLAGS=$(ALL_CFLAGS) -I/usr/include/rpm -lpq -lrpm -lrpmio -I$(LOCALAGENTDIR) -lcunit $(CUNIT_LIB) $(TEST_LIB)
EXE=test_pkgagent
LOCAL_RPM_4_4_CFLAGS=$(LOCAL_CFLAGS) -D_RPM_4_4
LOCAL_RPM_CFLAGS=$(LOCAL_CFLAGS) -D_RPM_4_4_COMPAT
TEST_OBJ=testRun.o testGetFieldValue.o testRecordMetadataRPM.o testRecordMetadataDEB.o testGetMetadataDebSource.o testGetMetadataDebBinary.o
all: $(EXE)
$(EXE): $(TEST_OBJ) testGetMetadata.o pkgagent.o $(VARS) cunit_lib test_lib
	$(CC) -o $@ $(LOCALAGENTDIR)/pkgagent.o $(TEST_OBJ) testGetMetadata.o $(LOCAL_CFLAGS) $(FO_LDFLAGS) $(DEFS)

$(TEST_OBJ): %.o: %.c
	$(CC) -c $(LOCAL_CFLAGS) $<

testGetMetadata.o:testGetMetadata.c
	if expr `rpm --version|awk '{print $$3}'|awk -F. '{print $$1"."$$2}'` \>= 4.5 >/dev/null ; then \
		$(CC) -c $(LOCAL_RPM_CFLAGS) $<; \
	else \
		$(CC) -c $(LOCAL_RPM_4_4_CFLAGS) $<; \
	fi

test: all $(EXE)
	./$(EXE)

cunit_lib:
	$(MAKE) -C $(CUNIT_LIB_DIR)

test_lib:
	$(MAKE) -C $(TEST_LIB_DIR)

coverage: $(TEST_OBJ) testGetMetadata.o pkgagent_cov.o cunit_lib test_lib
	$(CC) -o $(EXE) $(LOCALAGENTDIR)/pkgagent_cov.o $(TEST_OBJ) testGetMetadata.o $(COV_CFLAGS) $(LOCAL_CFLAGS); \
	./$(EXE); \
	lcov --directory  $(LOCALAGENTDIR)/   --capture --output-file cov.txt; \
	genhtml  -o  results cov.txt

pkgagent_cov.o:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

pkgagent.o:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

clean:
	rm -fr $(EXE) *.o core *.xml *.txt results

