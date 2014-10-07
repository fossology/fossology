######################################################################
# Copyright (C) 2010-2012 Hewlett-Packard Development Company, L.P.
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
DEPS = $(TOP)/Makefile.deps
include $(VARS)

#GLIB_FLAGS = -lgthread-2.0 -lgio-2.0 -I/usr/include/libgtop-2.0/ -lgtop-2.0
AR = ar
GLIB_FLAGS = -lgthread-2.0 -lgio-2.0 -lgobject-2.0
CFLAGS_LOCAL = $(GLIB_FLAGS) $(ALL_CFLAGS) -I. -lpq -lpthread
DEPEN = Makefile logging.h
DEF = -DLOG_DIR='"$(LOGDIR)"' \
      -DDEFAULT_SETUP='"$(SYSCONFDIR)"' \
      -DCLI_DIR='"$(DESTDIR)$(BINDIR)"' \
      -DFOSSDB_CONF='"$(SYSCONFDIR)/$(PROJECT)/Db.conf"'

LIB = libscheduler.a
COV = libscheduler_cov.a
EXE = fo_scheduler fo_cli
FODEF = -DPROJECT_USER='"$(PROJECTUSER)"' \
        -DPROJECT_GROUP='"$(PROJECTGROUP)"' \
        -DPROCESS_NAME='"$(EXE)"'

INITFILE = init.d/fossology
CONFDIR = $(DESTDIR)$(SYSCONFDIR)

#######################
# objects and headers #
#######################

OBJS = scheduler.o \
       agent.o \
       database.o \
       event.o \
       host.o \
       interface.o \
       job.o \
       logging.o

COVERAGE = $(OBJS:%.o=%_cov.o)

HEAD = agent.h \
       event.h \
       scheduler.h \
       host.h \
       job.h \
       logging.h \
       interface.h \
       sqlstatements.h

##########################
# executable build rules #
##########################

all: $(FOLIB) $(EXE) $(CLI) defconf/init.d/fossology

$(EXE): %: %.c $(LIB) $(VARS) $(HEAD) $(DEPEN) $(FOLIB)
	$(CC) $< $(LIB) $(DEF) $(CFLAGS_LOCAL) -o $@

$(LIB): $(OBJS) $(VARS) $(HEAD) $(DEPEN)
	$(AR) cr $@ $(OBJS)

$(COV): $(COVERAGE) $(VARS) $(HEAD) $(DEPEN)
	$(AR) cr $@ $(COVERAGE)

##################
# init.d scripts #
##################

include $(TOP)/Makefile.process
defconf/init.d/fossology: defconf/init.d/fossology-process

######################
# object build rules #
######################

main.o: %.o: %.c $(HEAD)
	$(CC) -c $(CFLAGS_LOCAL) $(FODEF) $(DEF) $<

scheduler.o: %.o: %.c %.h $(HEAD) $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(FODEF) $(DEF) $<

agent.o: %.o: %.c %.h job.h database.h scheduler.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

database.o: %.o: %.c %.h agent.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

event.o: %.o: %.c %.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

host.o: %.o: %.c %.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

interface.o: %.o: %.c %.h database.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

job.o: %.o: %.c %.h agent.h database.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

logging.o: %.o: %.c $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

$(COVERAGE): %_cov.o: %.c $(HEAD) $(DEPEN)
	$(CC) -c $< $(CFLAGS_LOCAL) $(FLAG_COV) $(FODEF) $(DEF) -o $@

#######################
# install build rules #
#######################

install: all install-conf
	$(INSTALL_PROGRAM) fo_scheduler $(DESTDIR)$(MODDIR)/scheduler/agent/fo_scheduler
	$(INSTALL_PROGRAM) fo_cli $(DESTDIR)$(MODDIR)/scheduler/agent/fo_cli
	$(INSTALL_PROGRAM) defconf/$(INITFILE) $(DESTDIR)$(INITDIR)/$(INITFILE)
	@if test ! -e $(CONFDIR)/mods-enabled/scheduler; then \
		ln -s $(MODDIR)/scheduler $(CONFDIR)/mods-enabled; \
	fi

install-conf:
	@mkdir -p $(CONFDIR)/mods-enabled
	#@echo "Installing config files in $(CONFDIR)/"
	#@if [ ! -f $(CONFDIR)/$(CONFFILE) -o "$(OVERWRITE)" ]; then \
	#	mkdir -p $(CONFDIR)/$(dir $(CONFFILE)); \
	#	cp $(CONFFILE) $(CONFDIR)/; \
	#fi
	@echo "Installing init script in $(DESTDIR)$(INITDIR)/"
	@if [ ! -f $(DESTDIR)$(INITDIR)/$(INITFILE) ]; then \
		mkdir -p $(DESTDIR)$(INITDIR)/$(dir $(INITFILE)); \
	fi
	$(INSTALL_PROGRAM) defconf/$(INITFILE) $(DESTDIR)$(INITDIR)/$(INITFILE)

#####################
# other build rules #
#####################

clean:
	rm -f $(MAIN) $(EXE) $(CLI) *.o core *.a *.gc* defconf/init.d/fossology

uninstall:
	rm -f $(OBJS) *.a
	rm -rf $(DESTDIR)$(MODDIR)/scheduler defconf/init.d/fossology
	rm -rf $(DESTDIR)$(MODDIR)/schedulerCLI
	rm -f $(CONFDIR)/mods-enabled/scheduler
	rm -f $(CONFDIR)/mods-enabled/fo_cli

test: all
	@echo "NOTICE: no tests available for 2.0 scheduler"

coverage:
	@echo "NOTICE: no coverage available for 2.0 scheduler"

include $(DEPS)

.PHONY: all install uninstall clean test coverage
