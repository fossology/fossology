# SPDX-FileCopyrightText: © 2014 Siemens AG

# SPDX-License-Identifier: FSFAP

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

LOCALAGENTDIR = ../../agent

all:

test:
	$(PHPUNIT) --bootstrap $(PHPUNIT_BOOT) *.php

coverage: test

clean:

.PHONY: all test coverage clean
