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
#
# ui_tests makefile
#
TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DIRS =  ui_tests
TESTDIR = ui_tests

dirloop=for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1);  done

all: 
	echo "all: Nothing to do for ui_tests"

test: 
	echo "test: Place holder for launching UI functional tests"

coverage:
	echo "No make coverage for ui_tests"

install: all
	echo "No install needed for UI Functional tests"

uninstall:
	echo "Nothinhg to uninstall for UI Functional tests"

clean:
	echo "Place holder for clean, UI Functional tests"

.PHONY: all install uninstall clean test 
