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

CFLAGS_LOCAL = $(CFLAGS_DB) $(CFLAGS_REPO) $(CFLAGS_AGENT) $(CFLAGS_SCHED) $(ALL_CFLAGS) -I. -lpq -lpcre
DEF = -DDATADIR='"$(DATADIR)"'

EXE = copyright
TESTDIR = tests

OBJECTS  = copyright.o \
           cvector.o \
		   radixtree.o \
		   pair.o \

COVERAGE = copyright_cov.o \
           cvector_cov.o \
           radixtree_cov.o \
           pair_cov.o \

##########################
# executable build rules #
##########################
		 
all: $(EXE)

test: $(OBJECTS)
	$(MAKE) -C $(TESTDIR) test

coverage: $(COVERAGE)
	$(MAKE) -C $(TESTDIR) coverage

$(EXE): main.c libcopyright.a $(DB) $(REPO) $(AGENTLIB) $(VARS)
	$(CC) main.c libcopyright.a $(DEF) $(CFLAGS_LOCAL) -o $@
	
#######################
# library buidl rules #
#######################

libcopyright.a: $(OBJECTS)
	ar cvr $@ $(OBJECTS)
	
libcopyright_cov.a: $(COVERAGE)
	ar cvr $@ $(COVERAGE)

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

########################
# coverage build rules #
########################

copyright_cov.o: copyright.c copyright.h cvector.h radixtree.h
	$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL) $(DEF) -o $@

cvector_cov.o: cvector.c cvector.h
	$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL) -o $@

radixtree_cov.o: radixtree.c radixtree.h
	$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL) -o $@

pair_cov.o:  pair.c pair.h cvector.h
	$(CC) -c $< $(FLAG_COV) $(CFLAGS_LOCAL) -o $@

#######################
# install build rules #
#######################

install: all
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(AGENTDIR)/
	$(INSTALL_PROGRAM) copyright.dic $(DATADIR)/
	$(INSTALL_PROGRAM) names.dic $(DATADIR)/
	make -C ui-plugin install

uninstall:
	rm -f $(DESTDIR)$(AGENTDIR)/$(EXE)/$(EXE)
	rm -f $(DATADIR)/copyright.dic
	rm -f $(DATADIR)/names.dic
	make -C ui-plugin uninstall

#####################
# other build rules #
#####################

clean:
	rm -f *.o *.gcno *.gcda *.a $(EXE)
	rm -f False_Positives False_Negatives Matches Found
	$(MAKE) -C tests/ clean

include $(DEPS)

.PHONY: all install uninstall clean test
	
