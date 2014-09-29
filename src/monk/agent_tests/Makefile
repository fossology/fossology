# Copyright Siemens AG 2014
#
# Copying and distribution of this file, with or without modification,
# are permitted in any medium without royalty provided the copyright
# notice and this notice are preserved.  This file is offered as-is,
# without any warranty.

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

DIRS = Unit Functional

dirloop=for dir in $(DIRS); do $(MAKE) -s -C $$dir $(1);  done

all:
	$(call dirloop, )

test:
	$(call dirloop, test)

coverage:
	$(call dirloop, coverage)

clean:
	$(call dirloop, clean)

install:
	echo "no make copyright ui install"

uninstall:
	echo "no make copyright ui uninstall"

PHONY: all test coverage clean install uninstall

