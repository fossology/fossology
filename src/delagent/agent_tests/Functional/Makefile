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
