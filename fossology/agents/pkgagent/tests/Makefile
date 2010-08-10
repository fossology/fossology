# FOSSology Makefile - test for agents/pkgagent
# Copyright (C) 2009 Hewlett-Packard Development Company, L.P.

TEST_FILES=pkgagent

all: $(TEST_FILES)
	$(MAKE) -C $(TEST_FILES) all

test: $(TEST_FIlES)
	$(MAKE) -C $(TEST_FILES) test

coverage: test
	$(MAKE) -C $(TEST_FILES) coverage 

clean:
	$(MAKE) -C $(TEST_FILES) clean

