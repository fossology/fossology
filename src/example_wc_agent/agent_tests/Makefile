# SPDX-FileCopyrightText: © 2011 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

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

.PHONY: all test coverage clean
