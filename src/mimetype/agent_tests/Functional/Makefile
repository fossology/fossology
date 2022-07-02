# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

TESTFILE = cliParamsTest4Mimetype.php
EXE_DIR = ../../agent/

all:
	$(MAKE) -C $(EXE_DIR)

test: all
	phpunit --log-junit mimetype-Xunit.xml $(TESTFILE)

coverage:
	@echo "nothing to do"

clean:
	rm -f *.xml

.PHONY: all test coverage clean
