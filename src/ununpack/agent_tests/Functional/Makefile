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
include $(VARS)
DATA=../unpack-test-data.tar.bz2

TESTFILE = tests.xml 
AGENTDIR = ../../agent/

all:
	$(MAKE) -C $(AGENTDIR)/ $@

$(DATA):
	@echo "Downloading ununpack data"
	rsync -acv fonightly.usa.hp.com:/var/www/testfiles/unpack-test-data.tar.bz2 ..

download: ../unpack-test-data.tar.bz2
	@echo "untaring ununpack data"
	cd ..; \
	tar -xf unpack-test-data.tar.bz2; \
	cd Functional

test: all download
	@echo "ununpack agent functional test"
	phpunit --log-junit ununpack-Xunit.xml --configuration $(TESTFILE)

coverage:
	@echo "no coverage for ununpack/agent_test/Functional"


clean:
	rm -rf ununpack-Xunit.xml  ../unpack-test-data.tar.bz2 ../test-data

.PHONY: all test coverage clean
