######################################################################
# Copyright (C) 2012-2014 Hewlett-Packard Development Company, L.P.
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

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

all:

test: fossology_testconfig phpvendors
	@echo "make functional test for cli scripts"
	$(MAKE) -C $(TOP)
	phpunit --log-junit cli-Xunit.xml --configuration tests.xml

coverage:
	@echo "no coverage for cli/tests"

clean:
	rm -f cli-Xunit.xml

phpvendors:
	$(MAKE) -C $(TOP)/src $@

.PHONY: all test coverage clean

include $(DEPS)
