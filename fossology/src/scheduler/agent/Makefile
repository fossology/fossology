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

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

GLIB_FLAGS = `pkg-config --cflags --libs glib-2.0` -lgthread-2.0 -lgio-2.0 -I/usr/include/libgtop-2.0/ -lgtop-2.0
CFLAGS_LOCAL = $(GLIB_FLAGS) $(ALL_CFLAGS) -I. -lpq -lpthread
DEF   = -DAGENT_DIR='"$(DESTDIR)$(AGENTDIR)"' -DLOG_DIR='"$(LOGDIR)"' \
        -DDEFAULT_SETUP='"$(SYSCONFDIR)/fossology"' -DCLI_DIR='"$(DESTDIR)$(BINDIR)"'
DEPEN = Makefile logging.h

EXE = fo_scheduler
CLI = fo_cli
FODEF = -DPROJECT_USER='"$(PROJECTUSER)"' -DPROJECT_GROUP='"$(PROJECTGROUP)"' -DPROCESS_NAME='"$(EXE)"'

INITFILE=init.d/fossology
CONFFILE=fossology.conf

#######################
# objects and headers #
#######################

OBJS = agent.o \
       database.o \
       event.o \
       host.o \
       interface.o \
       job.o \
       logging.o \

HEAD = agent.h \
       event.h \
       scheduler.h \
       host.h \
       job.h \
       logging.h \
       interface.h \
       
##########################
# executable build rules #
##########################

all: $(EXE) $(CLI) defconf/init.d/fossology

$(EXE): scheduler.c $(OBJS) $(VARS) $(HEAD) $(DEPEN)
	$(CC) scheduler.c $(OBJS) $(DEF) $(FODEF) $(CFLAGS_LOCAL) -o $@

$(CLI): schedulerCLI.c Makefile
	$(CC) schedulerCLI.c $(CFLAGS_LOCAL) $(DEF) -o $@

#######################
# library build rules #
#######################

libscheduler.a: $(OBJS) $(DEPEN)
	ar cvr $@ $(OBJS)

##################
# init.d scripts #
##################

include $(TOP)/Makefile.process
defconf/init.d/fossology: defconf/init.d/fossology-process

######################
# object build rules #
######################

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

#######################
# install build rules #
#######################

install: all install-conf
	$(INSTALL_PROGRAM) $(EXE) $(DESTDIR)$(LIBEXECDIR)/$(EXE)
	$(INSTALL_PROGRAM  $(CLI) $(DESTDIR)$(LIBEXECDIR)/$(CLI)
	$(INSTALL_PROGRAM) defconf/$(INITFILE) $(DESTDIR)$(INITDIR)/$(INITFILE)

install-conf:
	@ echo "Installing config files..."
	@if [ ! -f $(DESTDIR)$(SYSCONFDIR)/fossology/$(CONFFILE) -o "$(OVERWRITE)" ] ; then \
	  mkdir -p $(DESTDIR)$(SYSCONFDIR)/fossology/$(dir $(CONFFILE)); \
	  cp $(CONFFILE) $(DESDIR)$(SYSCONFDIR)/fossology/; \
	fi
	@ echo "Installing init script..."
	@if [ ! -f $(DESTDIR)$(INITDIR)/$(INITFILE) ] ; then \
	  mkdir -p $(DESTDIR)$(INITDIR)/$(dir $(INITFILE)); \
	fi
	$(INSTALL_PROGRAM) defconf/$(INITFILE) $(DESTDIR)$(INITDIR)/$(INITFILE);

#####################
# other build rules #
#####################

clean:
	rm -f $(OBJS) *.a
	rm -f $(EXE) $(CLI) defconf/init.d/fossology

test: all
	@echo "NOTICE: no tests available for 2.0 scheduler"

coverage:
	@echo "NOTICE: no coverage available for 2.0 scheduler"

include $(DEPS)

.PHONE: all install uninstall clean test coverage


