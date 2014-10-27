# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

EXE_DIR = ../../agent/

all: $(FOLIB)
	$(MAKE) -C $(EXE_DIR)

test: all
	./shunit2 cli_test.sh

coverage:
	@echo "nothing to do"

clean:
	@echo "nothing to do"

.PHONY: all test coverage clean

include $(DEPS)
