# FOSSology Makefile - test for agents/ununpack
# Copyright (C) 2009 Hewlett-Packard Development Company, L.P.

DIRS=ununpack ununpack-ar ununpack-iso ununpack-disk
BUILDDIRS = $(DIRS:%=build-%)
CLEANDIRS = $(DIRS:%=clean-%)
TESTDIRS = $(DIRS:%=test-%)
COVDIRS = $(DIRS:%=cov-%)

all: $(BUILDDIRS)
$(BUILDDIRS):
	$(MAKE) -C $(@:build-%=%) all

test: $(TESTDIRS)
$(TESTDIRS):
	$(MAKE) -C $(@:test-%=%) test

coverage: $(COVDIRS)
$(COVDIRS):
	$(MAKE) -C $(@:cov-%=%) coverage 

clean: $(CLEANDIRS)
$(CLEANDIRS):
	$(MAKE) -C $(@:clean-%=%) clean

utility.o: utility.c
	$(CC) -c utility.c -I../
