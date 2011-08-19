# FOSSology Makefile - test for mimetype 
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
TOP=../../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
LOCALAGENTDIR=../../agent

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lmagic -lpq $(ALL_CFLAGS) -I$(LOCALAGENTDIR)/ -I./ -lcunit
EXE=test_mimetype
TEST_OBJ_RUN=testRun.o
TEST_OBJ= ./finder/testDBCheckMime.o \
          ./finder/testDBLoadMime.o \
          ./finder/testDBFindMime.o \
          ./finder/testOtheFunctions.o

all: $(EXE)

$(EXE): libmimetype.a $(TEST_OBJ_RUN) $(TEST_OBJ) $(DB) $(REPO) $(VARS)
	@echo "make mimetype agent unit test"
	$(CC) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/libmimetype.a $(CFLAGS_LOCAL) -o $@

$(TEST_OBJ_RUN): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $<

$(TEST_OBJ): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@
	
test: all $(EXE)
	@echo "execute mimetype test"
	./$(EXE)

coverage: libmimetype_cov.a $(TEST_OBJ_RUN) $(TEST_OBJ) $(DB) $(REPO) $(VARS) 
	@echo "execute mimetype test and code coverage"
	$(CC) -o $(EXE) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/libmimetype_cov.a $(FLAG_COV) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR)/ --capture --output-file cov.txt; \
	genhtml  -o results cov.txt

libmimetype_cov.a:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

libmimetype.a:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

clean:
	rm -fr $(EXE) $(TEST_OBJ) $(TEST_OBJ_RUN) core *.xml *.txt  *.gc* results test_result 

include $(DEPS)
