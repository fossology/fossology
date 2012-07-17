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

AGENT_DIR   = ../../agent
TEST_AGENTS = ../agents/

regression = ./Functional.py
test_src = Functional.xml
test_dst = scheduler-Xunit.xml

##########################
# executable build rules #
##########################

all:

test: fossology_testconfig
	$(MAKE) -C $(TOP)/src
	$(MAKE) -C $(AGENT_DIR)/
	$(MAKE) -C $(TEST_AGENTS)/ test
	$(regression) --tests=$(test_src) --results=$(test_dst)

#####################
# other build rules #
#####################

clean:
	rm -f $(test_dst) *.pyc

include $(DEPS)

.PHONY: all test $(PHP_FILES) $(FO_LIB)
