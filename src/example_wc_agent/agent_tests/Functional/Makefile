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

MOD_NAME = example_wc_agent
MOD_SUBDIR = agent_tests
MOD_TEST_DIR = Functional

all:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR)/$(MOD_TEST_DIR)"

test: all
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR)/$(MOD_TEST_DIR) test"

coverage: test
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR)/$(MOD_TEST_DIR) coverage"

clean:
	@echo "make $(MOD_NAME)/$(MOD_SUBDIR)/$(MOD_TEST_DIR) clean"

.PHONY: all test coverage clean
