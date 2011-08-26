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

TESTFILES = test_common_sysconfig.php 

all:

test: all
	@echo "make unit test commom php files"
	for TESTFILE  in $(TESTFILES); do (phpunit --log-junit $$TESTFILE-Xunit.xml $$TESTFILE); done

coverage:
	@echo "make unit coverage commom php files"
	for TESTFILE  in $(TESTFILES); do (phpunit --coverage-html ./result $$TESTFILE); done

clean:
	rm -rf *.xml result

.PHONY: all test coverage clean
