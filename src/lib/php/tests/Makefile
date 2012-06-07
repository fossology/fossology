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

all: 

test: all
	@echo "make unit test for commom php files"
	phpunit --log-junit lib-php-Xunit.xml -c tests.xml

coverage: all
	@echo "make unit coverage for commom php files"
	phpunit --coverage-html ./results -c tests.xml

clean:
# note do not remove *.xml, it remove the php unit config file tests.xml
	rm -rf lib-php-Xunit.xml results

.PHONY: all test coverage clean 
