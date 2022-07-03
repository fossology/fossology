# SPDX-FileCopyrightText: © 2010-2012 Hewlett-Packard Development Company, L.P.
# SPDX-FileCopyrightText: © 2015, 2018 Siemens AG

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
DEPS = $(TOP)/Makefile.deps
include $(VARS)

#GLIB_FLAGS = -lgthread-2.0 -lgio-2.0 -I/usr/include/libgtop-2.0/ -lgtop-2.0
GLIB_FLAGS = -lgthread-2.0 -lgio-2.0 -lgobject-2.0
LDFLAGS_LOCAL = $(GLIB_FLAGS) $(FO_LDFLAGS) -lpthread
CFLAGS_LOCAL = $(FO_CFLAGS) -I.
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
       logging.o \
       emailformatter.o

COVERAGE = $(OBJS:%.o=%_cov.o)

HEAD = agent.h \
       event.h \
       scheduler.h \
       host.h \
       job.h \
       logging.h \
       interface.h \
       sqlstatements.h \
       emailformatter.h

##########################
# executable build rules #
##########################

all: $(FOLIB) $(EXE) $(CLI) defconf/init.d/fossology

$(EXE): %: %.c $(LIB) $(VARS) $(HEAD) $(DEPEN) $(FOLIB)
	$(CC) $< $(LIB) $(DEF) $(CFLAGS_LOCAL) $(LDFLAGS_LOCAL) -o $@

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

emailformatter.o: %.o: %.c agent.h $(DEPEN)
	$(CC) -c $(CFLAGS_LOCAL) $(DEF) $<

$(COVERAGE): %_cov.o: %.c $(HEAD) $(DEPEN)
	$(CC) -c $< $(CFLAGS_LOCAL) $(FLAG_COV) $(FODEF) $(DEF) -o $@

#######################
# install build rules #
#######################

install: all install-conf
	$(INSTALL_PROGRAM) fo_scheduler $(DESTDIR)$(MODDIR)/scheduler/agent/fo_scheduler
	$(INSTALL_PROGRAM) fo_cli $(DESTDIR)$(MODDIR)/scheduler/agent/fo_cli
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

uninstall:
	rm -f $(OBJS)
	rm -rf $(DESTDIR)$(MODDIR)/scheduler defconf/init.d/fossology
	rm -rf $(DESTDIR)$(MODDIR)/schedulerCLI
	rm -f $(CONFDIR)/mods-enabled/scheduler
	rm -f $(CONFDIR)/mods-enabled/fo_cli

clean:
	rm -f $(MAIN) $(EXE) $(CLI) *.o core *.a *.gc* defconf/init.d/fossology

.PHONY: all install uninstall clean

include $(DEPS)
