# SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

EXE_DIR = ../../agent/

all: $(FOLIB)
	$(MAKE) -C $(EXE_DIR)

all-coverage: $(FOLIB)
	$(MAKE) -C $(EXE_DIR) coverage

test: all test-run

coverage: all-coverage test-run

test-run:
	$(PHPUNIT) --log-junit nomos-Xunit.xml --configuration tests.xml
#	./shunit2 --plugin example OneShot_test.sh

# note DO NOT put *.xml in the clean rule; it will remove the tests.xml file
# which is needed to run the tests!
clean:
	rm -f nomos-Xunit.xml nomos-shunit-Xunit.xml
	rm -f fossology.rc
	rm -f scan.out* report.d* LastGoodNomosTestfilesScan.s output.txt

.PHONY: all test coverage clean

include $(DEPS)
