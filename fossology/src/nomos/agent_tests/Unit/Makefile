# FOSSology Makefile - test for mimetype 
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
TOP=../../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
LOCALAGENTDIR=../../agent
CUNIT_LIB_DIR=$(TOP)/src/testing/lib/c
CUNIT_LIB= -L $(CUNIT_LIB_DIR) -l focunit -I $(CUNIT_LIB_DIR)


CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lmagic -lpq $(ALL_CFLAGS) -I$(LOCALAGENTDIR)/ -I./ $(CUNIT_LIB) -lcunit
EXE=
TEST_OBJ_RUN=
TEST_OBJ=
all: $(EXE)

$(EXE): 
	@echo "make nomos agent unit test"

$(TEST_OBJ_RUN):

$(TEST_OBJ): 

cunit_lib:
	
test: all 
	@echo "execute nomos unit test"
	@echo "NO TESTS (unit) for nomos"
coverage: 
	@echo "execute nomos test and code coverage"
	@echo "NO TESTS (unit) for nomos"

libmimetype_cov.a:

libmimetype.a:

clean:
	rm -fr $(EXE) $(TEST_OBJ) $(TEST_OBJ_RUN) core *.xml *.txt  *.gc* results test_result 

include $(DEPS)

.PHONY: all install uninstall clean test
