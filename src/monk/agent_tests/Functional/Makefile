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

LOCALAGENTDIR = ../../agent

all: $(FOLIB)
	$(MAKE) -C $(LOCALAGENTDIR)

test: all
	ln -fs $(LOCALAGENTDIR)/monk ./monk
	./shunit2 cli_test.sh

coverage:
	$(MAKE) -C $(LOCALAGENTDIR) monk_cov
	ln -fs $(LOCALAGENTDIR)/monk_cov ./monk
	./shunit2 cli_test.sh
	@echo TODO call phpunit
	lcov --directory $(LOCALAGENTDIR) --capture --output-file cov.txt
	genhtml -o results cov.txt

clean:
	@echo "nothing to do"

.PHONY: all test coverage clean

include $(DEPS)
