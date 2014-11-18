# FOSSology Makefile - db/
# Copyright (C) 2008-2014 Hewlett-Packard Development Company, L.P.

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

all: dbcreate

# include the preprocessing stuff
include $(TOP)/Makefile.process
# generate the dbcreate script
dbcreate: dbcreate-process
	chmod +x dbcreate

install: all
	$(INSTALL_DATA) fossologyinit.sql $(DESTDIR)$(LIBEXECDIR)/fossologyinit.sql
	$(INSTALL_DATA) licenseref.sql $(DESTDIR)$(LIBEXECDIR)/licenseref.sql
	$(INSTALL_DATA) dataProperties.dat $(DESTDIR)$(LIBEXECDIR)/dataProperties.dat
	$(INSTALL_DATA) dbmigrate_1.4-2.0.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_1.4-2.0.php
	$(INSTALL_DATA) dbmigrate_2.0-2.1.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.0-2.1.php
	$(INSTALL_DATA) dbmigrate_2.1-2.2.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.1-2.2.php
	$(INSTALL_DATA) dbmigrate_2.0-2.5-pre.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.0-2.5-pre.php
	$(INSTALL_DATA) dbmigrate_2.5-2.6.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.5-2.6.php
	$(INSTALL_DATA) sanity_check.php $(DESTDIR)$(LIBEXECDIR)/sanity_check.php
	$(INSTALL_DATA) fo_mapping_license.php $(DESTDIR)$(LIBEXECDIR)/fo_mapping_license.php

	$(INSTALL_PROGRAM) export_license_ref.php $(DESTDIR)$(LIBEXECDIR)/export_license_ref.php
	$(INSTALL_PROGRAM) dbcreate $(DESTDIR)$(LIBEXECDIR)/dbcreate
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
	rm -f $(DESTDIR)$(LIBEXECDIR)/licenseref.sql
	rm -f $(DESTDIR)$(LIBEXECDIR)/clearingProperties.*
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbcreate
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_1.4-2.0.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.0-2.1.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.1-2.2.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.5-2.6.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/export_license_ref.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/fo_mapping_license.php

	@if [ -d $(DESTDIR)$(LIBEXECDIR) ]; then \
		if [ "`ls -A $(DESTDIR)$(LIBEXECDIR)`" ]; then \
			echo "WARNING: $(DESTDIR)$(LIBEXECDIR) not empty, not removing"; \
		else \
			rmdir $(DESTDIR)$(LIBEXECDIR); \
		fi \
	fi

	@echo "WARNING: Cowardly refusing to remove the following"
	@echo "      $(DESTDIR)/etc/cron.d/fossology"
	@echo "  Remove by hand if you desire."

clean:
	rm -f core dbcreate

test: all
	@echo "NOTICE: no tests available for db"

coverage:
	@echo "NOTICE: no coverage available for db"

.PHONY: all install uninstall clean test
