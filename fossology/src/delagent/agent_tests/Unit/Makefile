# FOSSology Makefile - test for wget_agent
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
TOP=../../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
LOCALAGENTDIR=../../agent

CFLAGS_LOCAL=-lpq $(ALL_CFLAGS) -I$(LOCALAGENTDIR) -lcunit
EXE=test_delagent
TEST_OBJ_RUN=testRun.o
TEST_OBJ=testListFolders.o testDeleteFolders.o testReadParameter.o

all: $(EXE)

$(EXE): delagent.o $(TEST_OBJ_RUN) $(TEST_OBJ) $(VARS)
	@echo "make delagent agent unit test"
	$(CC) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/delagent.o $(CFLAGS_LOCAL) -o $@

$(TEST_OBJ_RUN): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $<

$(TEST_OBJ): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@
	
test: all $(EXE)
	@echo "execute delagent test"
	./$(EXE)

coverage: delagent_cov.o $(TEST_OBJ_RUN) $(TEST_OBJ) $(VARS) 
	@echo "execute delagent test and code coverage"
	$(CC) -o $(EXE) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/delagent_cov.o $(FLAG_COV) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR)/ --capture --output-file cov.txt; \
	genhtml  -o results cov.txt

delagent_cov.o:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

delagent.o:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

clean:
	rm -fr $(EXE) $(TEST_OBJ) $(TEST_OBJ_RUN) core *.xml *.txt  *.gc* results test_result 

include $(DEPS)
