#  SPDX-FileCopyrightText: © 2011-2014 Hewlett-Packard Development Company, L.P.

#  SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

TESTFILE = ft_cliDelagentTest.php
AGENTDIR = ../../agent

# the list of external test data files required by this test
# these will be found in ../testdata/
TEST_FILES = testdb_all.gz

all:
	$(MAKE) -C $(AGENTDIR) $@

test: all $(TEST_FILES:%=../testdata/%)
	phpunit --log-junit delagent-Xunit.xml $(TESTFILE)

coverage:
	@echo "nothing to do"

clean:
	rm -rf *.xml

.PHONY: all test coverage clean
