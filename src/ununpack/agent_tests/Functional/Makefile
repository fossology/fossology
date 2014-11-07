######################################################################
# Copyright (C) 2011 Hewlett-Packard Development Company, L.P.
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
DEPS = $(TOP)/Makefile.deps
include $(VARS)
DATA=../unpack-test-data.tar.bz2

TESTFILE = tests.xml
AGENTDIR = ../../agent/

# list of the external test data files required by this test
# UNLIKE MOST OTHER TESTS, these will be found in ../
TEST_FILES = unpack-test-data.tar.bz2

all:
	$(MAKE) -C $(AGENTDIR)/ $@

# target to retrieve remote test data if it doesn't already exist
# make sure your /etc/wgetrc file is configured correctly!
testdata:
	@echo "Retrieve test files"
	@# for each test file specified, look for it in ../testdata
	@# if it exists, do nothing.
	@# if it does not exist, look in /var/www/testfiles
	@# if not in /var/www/testfiles, wget it from fossology.org
	@$(foreach test_file, $(TEST_FILES), \
	    if [ -e ../$(test_file) ]; then \
	        echo "../$(test_file) exists"; \
	    elif [ -e /var/www/testfiles/$(test_file) ]; then \
	        echo "$(test_file) found in /var/www/testfiles"; \
	        cp /var/www/testfiles/$(test_file) ../; \
	    else \
	        echo "$(test_file) does not exist.  need to wget from fossology.org"; \
	        wget --directory-prefix=../ http://www.fossology.org/testdata/$(test_file); \
	    fi; \
	)

test: all testdata fossology_testconfig
	@echo "ununpack agent functional test"
	cd ..; \
	tar -xf unpack-test-data.tar.bz2; \
	cd ./Functional; \
	phpunit --log-junit ununpack-Xunit.xml --configuration $(TESTFILE)

coverage:
	@echo "no coverage for ununpack/agent_test/Functional"

clean:
	rm -rf ununpack-Xunit.xml  ../unpack-test-data.tar.bz2 ../test-data

include $(DEPS)

.PHONY: all test coverage clean
