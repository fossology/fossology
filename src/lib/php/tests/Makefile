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

TESTFILES = test_common_sysconfig.php \
						test_common_active.php \
						test_common_cli.php \
						test_common_cache.php \
						test_common_auth.php \
						test_common_parm.php \
						test_common_menu.php \
						test_common_pkg.php \
						test_common_dir.php \

all: pathinclude

test: all
	@echo "make unit test for commom php files"
	for TESTFILE  in $(TESTFILES); do (phpunit --log-junit $$TESTFILE-Xunit.xml $$TESTFILE); done

coverage: all
	@echo "make unit coverage for commom php files"
	for TESTFILE  in $(TESTFILES); do (phpunit --coverage-html ./results $$TESTFILE); done

clean:
	rm -rf *.xml results

pathinclude:
	make -C ../../../www/ui/


.PHONY: all test coverage clean pathinclude
