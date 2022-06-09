# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DIRS = c php cpp
TESTDIRS = c cpp

DIR_LOOP = @set -e; for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1); done
TESTDIR_LOOP = @set -e; for dir in $(TESTDIRS); do $(MAKE) -s -C $$dir $(1); done

all:
	$(call DIR_LOOP, )

test: all
	$(call TESTDIR_LOOP,$@)

coverage: all
	$(call TESTDIR_LOOP,$@)

install: all
	$(call DIR_LOOP,$@)

uninstall:
	$(call DIR_LOOP,$@)
	rm -rf $(DESTDIR)$(MODDIR)/lib

clean:
	$(call DIR_LOOP,$@)
	$(call TESTDIR_LOOP,$@)

.PHONY: all test coverage install uninstall clean
