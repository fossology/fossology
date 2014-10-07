# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

EXE_DIR = ../../agent/

all: $(FOLIB)
	$(MAKE) -C $(EXE_DIR)

test: all
	@echo "make monk agent functional test"
	cd ../Functional; \
	pwd; \
	./shunit2 cli_test.sh

coverage:
	@echo "no code coverage for monk in agent_test/Functional"

clean:

include $(DEPS)

.PHONY: all test coverage clean
