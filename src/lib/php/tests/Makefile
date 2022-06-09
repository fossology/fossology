# SPDX-FileCopyrightText: © 2011-2014 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

all:

test: all phpvendors
	@echo "make unit test for commom php files"
	phpunit --log-junit lib-php-Xunit-Results.xml --configuration tests.xml

coverage: all phpvendors
	@echo "make unit coverage for commom php files"
	phpunit --coverage-html ./results --configuration tests.xml

phpvendors:
	$(MAKE) -C $(TOP)/src $@

clean:
# note do not remove *.xml, it remove the php unit config file tests.xml
	rm -rf lib-php-Xunit-Results.xml results

.PHONY: all test coverage clean
