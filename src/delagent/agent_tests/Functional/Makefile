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
TEST_FILES = testdb_all.tar

all:
	$(MAKE) -C $(AGENTDIR) $@

# target to retrieve remote test data if it doesn't already exist
# make sure your /etc/wgetrc file is configured correctly!
testdata:
	@echo "Retrieve test files"
	@# for each test file specified, look for it in ../testdata
	@# if it exists, do nothing.
	@# if it does not exist, look in /var/www/testfiles
	@# if not in /var/www/testfiles, wget it from fossology.org
	mkdir -p ../testdata
	@$(foreach test_file, $(TEST_FILES), \
		if [ -e ../testdata/$(test_file) ]; then \
			echo "../testdata/$(test_file) exists"; \
		elif [ -e /var/www/testfiles/delagent/$(test_file) ]; then \
			echo "$(test_file) found in /var/www/testfiles/delagent"; \
			cp /var/www/testfiles/delagent/$(test_file) ../testdata/; \
		else \
			echo "$(test_file) does not exist. need to wget from fossology.org"; \
			wget --directory-prefix=../testdata http://www.fossology.org/testdata/delagent/$(test_file); \
		fi; \
	)

test: all testdata
	phpunit --log-junit delagent-Xunit.xml $(TESTFILE)

coverage:
	@echo "nothing to do"

clean:
	rm -rf *.xml ../testdata

.PHONY: all test coverage clean
