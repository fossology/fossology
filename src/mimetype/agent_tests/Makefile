# FOSSology Makefile - test for wget_agent
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
LOCALAGENTDIR=../agent

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq $(ALL_CFLAGS) -I$(LOCALAGENTDIR)/ -I./ -lcunit
EXE=test_mimetype
TEST_OBJ_RUN=
TEST_OBJ=

all: $(EXE)

$(EXE): 
	@echo "make wget agent tests"

$(TEST_OBJ_RUN): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $<

$(TEST_OBJ): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@ -g
	
test: all $(EXE)
	@echo "execute wget agent test"
	./$(EXE)

coverage: 
	@echo "execute wget agent test and code coverage"
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
