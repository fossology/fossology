# FOSSology Makefile - db/
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=..
VARS=$(TOP)/Makefile.conf
DEPS=$(TOP)/Makefile.deps
include $(VARS)

all:

install: all
	$(INSTALL_DATA) fossologyinit.sql $(DESTDIR)$(LIBEXECDIR)/fossologyinit.sql
	@if [ ! -f "$(DESTDIR)/etc/cron.d/fossology" -o "$(OVERWRITE)" ]; then \
	   mkdir -p $(DESTDIR)/etc/cron.d/; \
	   echo "NOTE: using default version for $(DESTDIR)/etc/cron.d/fossology"; \
	   $(INSTALL_DATA) db.cron $(DESTDIR)/etc/cron.d/fossology; \
	else \
	   echo "WARNING: $(DESTDIR)/etc/cron.d/fossology already exists."; \
	   echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi

uninstall:
	rm -f $(DESTDIR)$(LIBEXECDIR)/fossologyinit.sql

	@if [ -d $(DESTDIR)$(LIBEXECDIR) ]; then \
	   if [ "`ls -A $(DESTDIR)$(LIBEXECDIR)`" ]; then \
	      echo "WARNING: $(DESTDIR)$(LIBEXECDIR) not empty, not removing";\
	   else \
	      rmdir $(DESTDIR)$(LIBEXECDIR); \
	   fi; \
	fi

	@echo "WARNING: Cowardly refusing to remove the following"
	@echo "      $(DESTDIR)/etc/cron.d/fossology"
	@echo "  Remove by hand if you desire."

clean:
	rm -f core

test: all
	@echo "NOTICE: no tests available for db"

.PHONY: all install uninstall clean test
