######################################################################
# Copyright (C) 2011-2013 Hewlett-Packard Development Company, L.P.
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
DEPS=$(TOP)/Makefile.deps
include $(VARS)

EXE_DIR = ../../agent/

all: $(FOLIB)
	$(MAKE) -C $(EXE_DIR)

test: all
	@echo "make nomos agent functional test"
	cd ../Functional; \
	pwd; \
	ls -l; \
	phpunit --log-junit nomos-Xunit.xml --configuration tests.xml
#	./shunit2 --plugin example OneShot_test.sh

coverage:
	@echo "no code coverage for nomos in agent_test/Functional"

# note DO NOT put *.xml in the clean rule; it will remove the tests.xml file 
# which is needed to run the tests!
clean:
	rm -f nomos-Xunit.xml nomos-shunit-Xunit.xml
	rm -f fossology.rc
	rm -f scan.out* report.d* LastGoodNomosTestfilesScan.s output.txt

.PHONY: all test coverage clean

include $(DEPS)
