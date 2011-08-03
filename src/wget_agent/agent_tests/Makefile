# FOSSology Makefile - test for wget_agent
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
TOP=../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
LOCALAGENTDIR=../agent

CFLAGS_LOCAL=$(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) -lpq $(ALL_CFLAGS) -I$(LOCALAGENTDIR)/ -I./ -lcunit
EXE=test_wget_agent
TEST_OBJ_RUN=testRun.o utility.o
TEST_OBJ=./wget_agent/testGetURL.o \
         ./wget_agent/testSetEnv.o
CHKHDR=$(LOCALAGENTDIR)/../../ununpack/agent/checksum.h ../../ununpack/agent/md5.h ../../ununpack/agent/sha1.h
CHKSRC=$(CHKHDR:%.h=%.c)

all: $(EXE)

$(EXE): libwget_agent.a $(TEST_OBJ_RUN) $(TEST_OBJ) $(CHKHDR) $(CHKSRC) $(VARS) $(DB) $(REPO) $(AGENTLIB)
	@echo "make wget agent tests"
	$(CC) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/libwget_agent.a $(CHKSRC) $(CFLAGS_LOCAL) -o $@

$(TEST_OBJ_RUN): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $<

$(TEST_OBJ): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@ -g
	
test: all $(EXE)
	@echo "execute wget agent test"
	./$(EXE)

coverage: libwget_agent_cov.a $(TEST_OBJ_RUN) $(TEST_OBJ) $(CHKHDR) $(CHKSRC) $(VARS) $(DB) $(REPO) $(AGENTLIB)
	@echo "execute wget agent test and code coverage"
	$(CC) -o $(EXE) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/libwget_agent_cov.a $(CHKSRC) $(FLAG_COV) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR)/ --capture --output-file cov.txt; \
	genhtml  -o results cov.txt

libwget_agent_cov.a:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

libwget_agent.a:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

clean:
	rm -fr $(EXE) $(TEST_OBJ) $(TEST_OBJ_RUN) core *.xml *.txt  *.gc* results test-result 

include $(DEPS)
