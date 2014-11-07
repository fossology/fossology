######################################################################
# Copyright (C) 2011-2014 Hewlett-Packard Development Company, L.P.
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# version 2 as published by the Free Software Foundation.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
######################################################################
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
