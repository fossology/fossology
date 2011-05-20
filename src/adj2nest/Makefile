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
TOP = ../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)
include $(DEPS)

DIRS = agent agent_tests

TESTDIR = agent_tests

dirloop=for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1);  done
		 
all: 
	echo "make adj2nest"
	$(call dirloop, )

install: all
	echo "make adj2nest install"
	$(call dirloop,install)

uninstall:
	echo "make adj2nest uninstall"
	$(call dirloop,uninstall)

clean:
	echo "make adj2nest clean"
	$(call dirloop,clean)

.PHONY: all install uninstall clean test coverage
