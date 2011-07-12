#####################################################################
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
TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

CFLAGS_LOCAL = $(ALL_CFLAGS) -I. -lpcre
DEF = -DDATADIR='"$(DATADIR)"'
CONFDIR = $(DESTDIR)$(SYSCONFDIR)/fossology
MODULEDIR = $(shell dirname `pwd`)

EXE = copyright
TESTDIR = ../agent_tests

OBJECTS  = copyright.o cvector.o radixtree.o  pair.o

##########################
# executable build rules #
##########################
$(EXE): main.c $(FOLIB) $(VARS) $(OBJECTS)
	@echo "make copyright agent"
	$(CC) main.c $(OBJECTS) $(DEF) $(CFLAGS_LOCAL) -o $@

all: $(EXE) test
	@echo "make copyright agent and test"

test: 
	$(MAKE) -C $(TESTDIR) test
	$(MAKE) -C $(TESTDIR) coverage

#######################
# library build rules #
#######################
$(FOLIB):
	$(MAKE) -C $(FOLIBDIR)

libcopyright.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)
	
######################
# object build rules #
######################

copyright.o: %.o: %.c %.h cvector.h radixtree.h
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

cvector.o: %.o: %.c %.h cvector.h
	$(CC) -c $(CFLAGS_LOCAL) $<

radixtree.o: %.o: %.c %.h
	$(CC) -c $(CFLAGS_LOCAL) $<

pair.o: %.o: %.c %.h cvector.h
	$(CC) -c $(CFLAGS_LOCAL) $<


#######################
# install build rules #
#######################

install: $(EXE)
	@echo "make copyright agent install"
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/
	$(INSTALL_DATA) copyright.dic $(DESTDIR)$(DATADIR)/
	$(INSTALL_DATA) names.dic $(DESTDIR)$(DATADIR)/
	@if test ! -e $(CONFDIR)/mods-enabled/copyright; then \
	  ln -s $(MODULEDIR) $(CONFDIR)/mods-enabled ; fi;

uninstall:
	@echo "make copyright agent uninstall"
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)/$(EXE)
	rm -f $(DESTDIR)$(DATADIR)/copyright.dic
	rm -f $(DESTDIR)$(DATADIR)/names.dic
	rm -f $(CONFDIR)/mods-enabled/copyright

#####################
# other build rules #
#####################

clean:
	@echo "make copyright agent clean"
	rm -f *.o *.a $(EXE)

.PHONY: all install uninstall clean test $(FOLIB)
	
