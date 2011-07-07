# FOSSology Makefile - common/
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

CONFFILES=fossology.conf
CONFPATH=$(SYSCONFDIR)/fossology

all: fo-postinstall defconf/Db.conf

# include the preprocessing stuff
include $(TOP)/Makefile.process
# generate the postinstall script
fo-postinstall: fo-postinstall-process
	chmod +x fo-postinstall

# generate the default Db.conf file
defconf/Db.conf: defconf/Db.conf-process

install: all
	$(INSTALL_PROGRAM) fo-postinstall $(DESTDIR)$(LIBEXECDIR)/fo-postinstall

	@ echo "Installing configuration files..."
	if [ ! -e $(DESTDIR)$(CONFPATH) ] ; then \
		mkdir -p $(DESTDIR)$(CONFPATH); \
	fi
	
	if [ ! -e $(DESTDIR)$(CONFPATH)/agents.d ] ; then \
		mkdir -p $(DESTDIR)$(CONFPATH)/agents.d; \
		/bin/chmod 775 $(DESTDIR)$(CONFPATH)/agents.d; \
	fi
	
	if [ ! -f $(DESTDIR)$(CONFPATH)/Db.conf -o "$(OVERWRITE)" ] ; then \
		echo "NOTE: using default version for $(DESTDIR)$(CONFPATH)/Db.conf"; \
	 	$(INSTALL) -m 660 defconf/Db.conf $(DESTDIR)$(CONFPATH)/Db.conf; \
	 	/bin/chown root:fossy $(DESTDIR)$(CONFPATH)/Db.conf; \
	 	/bin/chmod 640 $(DESTDIR)$(CONFPATH)/Db.conf; \
	else \
		echo "WARNING: $(DESTDIR)$(CONFPATH)/Db.conf already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi \
	
	if [ ! -f $(DESTDIR)$(CONFPATH)/fossology.conf -o "$(OVERWRITE)"]; then \
		echo "NOTE: using default version for $(DESTDIR)$(CONFPATH)/fossology.conf"; \
	 	$(INSTALL) -m 660 defconf/fossology.conf $(DESTDIR)$(CONFPATH)/fossology.conf; \
	 	/bin/chmod 666 $(DESTDIR)$(CONFPATH)/fossology.conf; \
	else \
		echo "WARNING: $(DESTDIR)$(CONFPATH)/fossology.conf already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi \

uninstall:
	rm -f $(DESTDIR)$(LIBEXECDIR)/fo-postinstall

	@if [ -d $(DESTDIR)$(LIBEXECDIR) ]; then \
		if [ "`ls -A $(DESTDIR)$(LIBEXECDIR)`" ]; then \
			echo "WARNING: $(DESTDIR)$(LIBEXECDIR) not empty, not removing";\
		else \
			rmdir $(DESTDIR)$(LIBEXECDIR); \
		fi; \
	fi

	@echo "WARNING: Cowardly refusing to remove the following"
	@echo "      $(DESTDIR)$(CONFPATH)/Depth.conf"
	@echo "      $(DESTDIR)$(CONFPATH)/Hosts.conf"
	@echo "      $(DESTDIR)$(CONFPATH)/Proxy.conf"
	@echo "      $(DESTDIR)$(CONFPATH)/RepPath.conf"
	@echo "      $(DESTDIR)$(CONFPATH)/Db.conf"
	@echo "  Remove by hand if you desire."


clean:
	rm -f fo-postinstall defconf/Db.conf

test: all
	@echo "NOTICE: no tests available for common"

coverage:
        @echo "NOTICE: no coverage available for common""

.PHONY: all install uninstall clean test
