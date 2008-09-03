# scheduler Makefile
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CONFDEF=-DBINDIR='"$(LIBEXECDIR)"' -DDEFAULTSETUP='"$(SYSCONFDIR)/fossology/scheduler.conf"'
FODEF=-DPROJECTUSER='"$(PROJECTUSER)"' -DPROJECTGROUP='"$(PROJECTGROUP)"'
MSCDEF=-DDATADIR='"$(DATADIR)"' -DBINDIR='"$(LIBEXECDIR)"' -DPROJECTSTATEDIR='"$(PROJECTSTATEDIR)"'

# these objects need libfossdb
OBJ=scheduler.o spawn.o sockets.o agents.o hosts.o debug.o dberror.o selftest.o
# these objects need libfossrepo as well
REPOOBJ=dbstatus.o dbq.o

INITFILE=init.d/fossology
DEFFILE=default/fossology

all: fossology-scheduler mkschedconf defconf/init.d/fossology

fossology-scheduler: $(OBJ) $(REPOOBJ) $(VARS)
	$(CC) $(OBJ) $(REPOOBJ) $(CFLAGS_DB) $(CFLAGS_REPO) \
	-lpq $(ALL_CFLAGS) -o $@

mkschedconf: mkschedconf.c $(REPO) $(VARS)
	$(CC) $< $(MSCDEF) $(CFLAGS_REPO) $(ALL_CFLAGS) -o $@

# include the preprocessing stuff to generate the init script
include $(TOP)/Makefile.process
defconf/init.d/fossology: defconf/init.d/fossology-process

# targets for our needed objects
$(OBJ): %.o: %.c %.h $(DB) $(VARS)
	$(CC) -c $< $(CONFDEF) $(FODEF) $(CFLAGS_DBO) $(ALL_CFLAGS)

$(REPOOBJ): %.o: %.c %.h $(DB) $(REPO) $(VARS)
	$(CC) -c $< $(CONFDEF) $(CFLAGS_REPOO) $(CFLAGS_DBO) $(ALL_CFLAGS)


install: all
	$(INSTALL_PROGRAM) fossology-scheduler $(DESTDIR)$(LIBEXECDIR)/fossology-scheduler
	$(INSTALL_PROGRAM) mkschedconf $(DESTDIR)$(LIBEXECDIR)/mkschedconf

	@ echo "Installing default file..."
	if [ ! -f $(DESTDIR)$(SYSCONFDIR)/$(DEFFILE) -o "$(OVERWRITE)" ] ; then \
		mkdir -p $(DESTDIR)$(SYSCONFDIR)/$(dir $(DEFFILE)); \
		echo "NOTE: using default version for $(DESTDIR)$(SYSCONFDIR)/$(DEFFILE)"; \
	 	$(INSTALL_DATA) defconf/$(DEFFILE) $(DESTDIR)$(SYSCONFDIR)/$(DEFFILE); \
	else \
		echo "WARNING: $(DESTDIR)$(SYSCONFDIR)/$(DEFFILE) already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi \

	@ echo "Installing init script..."
	@if [ ! -f $(DESTDIR)$(INITDIR)/$(INITFILE) -o "$(OVERWRITE)" ] ; then \
		mkdir -p $(DESTDIR)$(INITDIR)/$(dir $(INITFILE)); \
		echo "NOTE: using default version for $(DESTDIR)$(INITDIR)/$(INITFILE)"; \
	 	$(INSTALL_PROGRAM) defconf/$(INITFILE) $(DESTDIR)$(INITDIR)/$(INITFILE); \
	else \
		echo "WARNING: $(DESTDIR)$(INITDIR)/$(INITFILE) already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi
		

uninstall:
	rm -f $(DESTDIR)$(LIBEXECDIR)/fossology-scheduler
	rm -f $(DESTDIR)$(LIBEXECDIR)/mkschedconf

	@if [ -d $(DESTDIR)$(LIBEXECDIR) ]; then \
		if [ "`ls -A $(DESTDIR)$(LIBEXECDIR)`" ]; then \
			echo "WARNING: $(DESTDIR)$(LIBEXECDIR) not empty, not removing";\
		else \
			rmdir $(DESTDIR)$(LIBEXECDIR); \
		fi; \
	fi

	@if [ -d $(DESTDIR)$(SYSCONFDIR)/fossology ]; then \
		if [ "`ls -A $(DESTDIR)$(SYSCONFDIR)/fossology`" ]; then \
			echo "WARNING: $(DESTDIR)$(SYSCONFDIR)/fossology not empty, not removing";\
		else \
			rmdir $(DESTDIR)$(SYSCONFDIR)/fossology; \
		fi; \
	fi

	@echo "WARNING: Cowardly refusing to remove the following"
	@echo "      $(DESTDIR)/etc/init.d/fossology"
	@echo "      $(DESTDIR)$(SYSCONFDIR)/default/fossology"
	@echo "  Remove by hand if you desire."

clean:
	rm -f $(OBJ) $(REPOOBJ) core fossology-scheduler mkschedconf defconf/init.d/fossology

test: all
	@echo "NOTICE: no tests available for scheduler"

include $(DEPS)

.PHONY: all install uninstall clean test
