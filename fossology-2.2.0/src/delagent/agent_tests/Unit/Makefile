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

TOP=../../../..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)
LOCALAGENTDIR=../../agent
CUNIT_LIB_DIR=$(TOP)/src/testing/lib/c
CUNIT_LIB= -L $(CUNIT_LIB_DIR) -l focunit -I $(CUNIT_LIB_DIR)
TEST_LIB_DIR=$(TOP)/src/testing/db/c
TEST_LIB= -L $(TEST_LIB_DIR) -l fodbreposysconf -I $(TEST_LIB_DIR)

CFLAGS_LOCAL=-lpq $(ALL_CFLAGS) -I$(LOCALAGENTDIR) -lcunit $(CUNIT_LIB) $(TEST_LIB)
EXE=test_delagent
TEST_OBJ_RUN=testRun.o
TEST_OBJ=testListFolders.o testDeleteFolders.o testReadParameter.o

# these are the external test data files needed by the tests
# at the moment they will be found in the delagent/ directory, but this
# may change as we organize the test data in a more consistent way
TEST_FILES = testdb_all.tar testrepo_files.tar testrepo_gold.tar

all: $(EXE)

# target to retrieve remote test data if it doesn't already exist
# make sure your /etc/wgetrc file is configured correctly!
testdata:
	@echo "Retrieve test files"
	@# for each test file specified, look for it in ../testdata
	@# if it exists, do nothing.
	@# if it does not exist, look in /var/www/testfiles
	@# if not in /var/www/testfiles, wget it from fossology.org
	@$(foreach test_file, $(TEST_FILES), \
	    if [ -e ../testdata/$(test_file) ]; then \
	        echo "../testdata/$(test_file) exists"; \
	    elif [ -e /var/www/testfiles/delagent/$(test_file) ]; then \
	        echo "$(test_file) found in /var/www/testfiles/delagent"; \
	        cp /var/www/testfiles/delagent/$(test_file) ../testdata; \
	    else \
	        echo "$(test_file) does not exist.  need to wget from fossology.org"; \
	        wget --directory-prefix=../testdata http://www.fossology.org/testdata/delagent/$(test_file); \
            fi; \
	)

$(EXE): delagent.o $(TEST_OBJ_RUN) $(TEST_OBJ) $(VARS) cunit_lib test_lib $(FOLIB)
	@echo "make delagent agent unit test"
	$(CC) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/delagent.o $(CFLAGS_LOCAL) -o $@

$(TEST_OBJ_RUN): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $<

$(TEST_OBJ): %.o: %.c 
	$(CC) -c $(CFLAGS_LOCAL) $< -o $@
	
test: all $(EXE) testdata
	@echo "execute delagent test"
	./$(EXE)

cunit_lib:
	$(MAKE) -C $(CUNIT_LIB_DIR)

test_lib:
	$(MAKE) -C $(TEST_LIB_DIR)

coverage: delagent_cov.o $(TEST_OBJ_RUN) $(TEST_OBJ) $(VARS) 
	@echo "execute delagent test and code coverage"
	$(CC) -o $(EXE) $(TEST_OBJ_RUN) $(TEST_OBJ) $(LOCALAGENTDIR)/delagent_cov.o $(FLAG_COV) $(CFLAGS_LOCAL); \
	./$(EXE); \
	lcov --directory $(LOCALAGENTDIR)/ --capture --output-file cov.txt; \
	genhtml  -o results cov.txt

delagent_cov.o:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

delagent.o:
	$(MAKE) -C $(LOCALAGENTDIR)/ $@

clean:
	rm -fr $(EXE) $(TEST_OBJ) $(TEST_OBJ_RUN) core *.xml *.txt  *.gc* results test_result 

include $(DEPS)
