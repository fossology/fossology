# FOSSology Makefile - test for agents/ununpack
# Copyright (C) 2009 Hewlett-Packard Development Company, L.P.

TEST_FILES=ununpack

all: $(TEST_FILES)
	$(MAKE) -C $(TEST_FILES) all

test: $(TEST_FIlES)
	$(MAKE) -C $(TEST_FILES) test

coverage:
	$(MAKE) -C $(TEST_FILES) coverage 

clean:
	$(MAKE) -C $(TEST_FILES) clean

utility.o: utility.c 
	$(CC) -c utility.c
