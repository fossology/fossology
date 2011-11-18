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

# Makefile for lib/php

TOP=../../..
VARS=$(TOP)/Makefile.conf
include $(VARS)
CONFDIR = $(DESTDIR)$(SYSCONFDIR)/fossology
MODULEDIR = $(shell dirname `pwd`)

FILES=`find . -type f | grep -v svn |grep -v tests | grep -E "(php)$$" `

all:

install: all

	
	mkdir -p $(MODDIR)/lib/php; \
	
	for file in $(FILES); do \
		$(INSTALL_DATA) $$file $(MODDIR)/lib/php/$$file; \
	done

uninstall:
	echo "Removing php libs"; \
	for file in $(FILES); do \
		echo "Removing php file$$file"; \
		rm -f $(MODDIR)/lib/php/$$file; \
	done
	
	rmdir $(MODDIR)/lib/php; \

clean:
	rm -f *.o core

.PHONY: all install uninstall clean test
