# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

AGENTDIR = ../../agent/

# list of the external test data files required by this test
# UNLIKE MOST OTHER TESTS, these will be found in ../testdata/
TEST_FILES = circularlinks.tar null_file test1.zip.tar.gz test.cab test.cpio.gz testdir.tar.gz test.iso test.jar test.pdf
TEST_FILES += fossI16L335U29.tar.bz2 test.tar.Z testtwo.zip test.zip emptydirs.tar test.ar test.cpio test.deb
TEST_FILES += test_1-1.dsc test.msi test.rar test.rpm test_1.orig.tar.gz testthree.zip test.z fossI16L518.7z
TEST_FILES += libununpack.a test.exe test_1-1.debian.tar.xz ext2file.fs ext3file.fs fatfile.fs ntfsfile.fs

../testdata/libununpack.a:
	cp $(AGENTDIR)/libununpack.a ../testdata/libununpack.a

../testdata/fossI16L335U29.tar.bz2:
	cp ../../../testing/dataFiles/TestData/archives/fossI16L335U29.tar.bz2 ../testdata/fossI16L335U29.tar.bz2

../testdata/fossI16L518.7z:
	cp ../../../testing/dataFiles/TestData/archives/fossI16L518.7z ../testdata/fossI16L518.7z

test: agent test-run

coverage: agent-coverage test-run

test-run: testdata $(TEST_FILES:%=../testdata/%)
	$(PHPUNIT) --log-junit ununpack-Xunit.xml --configuration tests.xml

agent:
	$(MAKE) -C $(AGENTDIR)

agent-coverage:
	$(MAKE) -C $(AGENTDIR) coverage

# target to get test data and test ununpack on them
testdata:
	@echo "Unpacking test files"
	@cd ../testdata && tar -xvjf testdata.tar.bz2

clean:
	rm -rf ununpack-Xunit.xml
	rm -rf ../testdata/*.dir ../testdata/*.xml ../testdata/*.meta

.PHONY: all test coverage clean

include $(DEPS)
