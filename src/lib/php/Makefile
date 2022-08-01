# SPDX-FileCopyrightText: © 2011-2012 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: LGPL-2.1-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

FILES = `find . -type f | grep -Ev "(svn|\/tests?)" | grep -E "(php|twig)$$"`

all: bootstrap services.xml

# generate the postinstall script
bootstrap: bootstrap-process_php

# generate service.xml
services.xml: services.xml-process

# include the preprocessing stuff
include $(TOP)/Makefile.process

install: all
	mkdir -p $(DESTDIR)$(MODDIR)/lib/php
	for file in $(FILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(MODDIR)/lib/php/$$file; \
	done
	$(INSTALL_DATA) services.xml $(DESTDIR)$(MODDIR)/lib/php/services.xml

uninstall:
	@echo "Removing php libs"
	rm -rf $(DESTDIR)$(MODDIR)/lib/php

clean:
	rm -f *.o core bootstrap.php services.xml

test:
	$(MAKE) -C tests test

coverage:
	$(MAKE) -C tests coverage

.PHONY: all install uninstall clean test
