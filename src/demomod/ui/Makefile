# SPDX-FileCopyrightText: © 2013 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP=../../../
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps

include $(VARS)

DIR = $(realpath .)
MOD_NAME=demomod
MOD_SUBDIR=ui

all:
	@echo "make nothing in $(DIR)"

test:
	@echo "make test nothing in $(DIR)"

coverage:
	@echo "make coverage nothing in $(DIR)"

install:
	@echo "make install in $(DIR)"
	mkdir -p $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)
	$(INSTALL_DATA) ./*.php $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

uninstall:
	@echo "make uninstall in $(DIR)"
	rm -rf $(DESTDIR)$(MODDIR)/$(MOD_NAME)/$(MOD_SUBDIR)

clean:
	@echo "make clean nothing in $(DIR)"

include $(DEPS)

.PHONY: all install uninstall clean test coverage
