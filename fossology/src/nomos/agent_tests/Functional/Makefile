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

TESTFILE = cli1Test.php OneShot-lgpl2.1.php
EXE_DIR = ../../agent/

all:
	$(MAKE) -C $(EXE_DIR)

test: all
	@echo "make nomos agent functional test"
	@if test ! -e ../testdata/RedHat.tar.gz ; then \
		rsync -acv fonightly.usa.hp.com:/var/www/testfiles/RedHat.tar.gz ../testdata/;\
	fi;
	cd ../testdata; \
	tar -xf RedHat.tar.gz; \
	cd ../Functional
		
	phpunit --log-junit nomos-Xunit.xml -c tests.xml

coverage:
	@echo "no code coverage for nomos in agent_test/Functional"

clean:
	rm -f *.xml

.PHONY: all test coverage clean
