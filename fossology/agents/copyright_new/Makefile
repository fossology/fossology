######################################################################
# Copyright (C) 2010 Hewlett-Packard Development Company, L.P.
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
TOP = ../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

CFLAGS_LOCAL = $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) $(ALL_CFLAGS) -I. -lpq -lpcre
DEF = -DDATADIR='"$(DATADIR)"'

EXE = copyright
TESTDIR = tests

OBJECTS = copyright.o \
          cvector.o \
		  radixtree.o \
		  pair.o \

##########################
# executable build rules #
##########################		 
all: $(EXE)

test: $(OBJECTS)
	$(MAKE) -C $(TESTDIR) test

coverage: libcopyright_cov.so
	$(MAKE) -C $(TESTDIR) coverage

$(EXE): main.c libcopyright.so $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) main.c libcopyright.so $(CFLAGS_LOCAL) -o $@

##############################
# shared library build rules #
##############################

libcopyright.so: $(OBJECTS) $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) -shared -fPIC $(CFLAGS_LOCAL) $(OBJECTS) -o $@

libcopyright_cov.so: $(OBJECTS) $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) -shared -fPIC $(CFLAGS_LOCAL) $(OBJECTS) -o $@

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
install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/$(EXE)/
	$(INSTALL_PROGRAM) copyright.dic $(DATADIR)/
	$(INSTALL_PROGRAM) names.dic $(DATADIR)/
	$(INSTALL_PROGRAM) libcopyright.so $(DESTDIR)$(LIBDIR)/

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)/$(EXE)
	rm -f $(DATADIR)/copyright.dic
	rm -f $(DATADIR)/names.dic
	rm -f $(DESTDIR)$(LIBDIR)/libcopyright.so

#####################
# other build rules #
#####################
clean:
	rm -f *.o *.so $(EXE)
	rm -f False_Positives False_Negatives Matches Found
	$(MAKE) -C tests/ clean

include $(DEPS)

.PHONY: all install uninstall clean test
	
