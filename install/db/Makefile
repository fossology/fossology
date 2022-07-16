# FOSSology Makefile - db/
# SPDX-FileCopyrightText: © 2008-2014 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

all: dbcreate db.cron dbmigrate_pfile_calculate_sha256.php dbmigrate_change_db_encoding.php

# include the preprocessing stuff
include $(TOP)/Makefile.process
# generate the dbcreate script
dbcreate: dbcreate-process
	chmod +x dbcreate

# generate db.cron
db.cron: db.cron-process

# generate the pfile hash script
dbmigrate_pfile_calculate_sha256.php:	dbmigrate_pfile_calculate_sha256-process_php
	chmod +x dbmigrate_pfile_calculate_sha256.php

# generate the recoding script
dbmigrate_change_db_encoding.php:	dbmigrate_change_db_encoding-process_php
	chmod +x dbmigrate_change_db_encoding.php

install: all
	$(INSTALL_DATA) fossologyinit.sql $(DESTDIR)$(LIBEXECDIR)/fossologyinit.sql
	$(INSTALL_DATA) licenseRef.json $(DESTDIR)$(LIBEXECDIR)/licenseRef.json
	$(INSTALL_DATA) dbmigrate_1.4-2.0.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_1.4-2.0.php
	$(INSTALL_DATA) dbmigrate_2.0-2.1.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.0-2.1.php
	$(INSTALL_DATA) dbmigrate_2.1-2.2.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.1-2.2.php
	$(INSTALL_DATA) dbmigrate_2.0-2.5-pre.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.0-2.5-pre.php
	$(INSTALL_DATA) dbmigrate_2.5-2.6.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.5-2.6.php
	$(INSTALL_DATA) dbmigrate_3.3-3.4.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_3.3-3.4.php
	$(INSTALL_DATA) dbmigrate_3.5-3.6.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_3.5-3.6.php
	$(INSTALL_DATA) dbmigrate_3.6-3.7.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_3.6-3.7.php
	$(INSTALL_DATA) dbmigrate_3.7-3.8.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_3.7-3.8.php
	$(INSTALL_DATA) dbmigrate_4.0-4.1.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_4.0-4.1.php
	$(INSTALL_DATA) dbmigrate_copyright-event.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_copyright-event.php
	$(INSTALL_DATA) dbmigrate_clearing-event.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_clearing-event.php
	$(INSTALL_DATA) dbmigrate_real-parent.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_real-parent.php
	$(INSTALL_DATA) dbmigrate_bulk_license.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_bulk_license.php
	$(INSTALL_DATA) sanity_check.php $(DESTDIR)$(LIBEXECDIR)/sanity_check.php
	$(INSTALL_DATA) fo_mapping_license.php $(DESTDIR)$(LIBEXECDIR)/fo_mapping_license.php
	$(INSTALL_DATA) dbmigrate_copyright-author.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_copyright-author.php
	$(INSTALL_DATA) dbmigrate_multiple_copyright_decisions.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_multiple_copyright_decisions.php
	$(INSTALL_DATA) resequence_author_table.php $(DESTDIR)$(LIBEXECDIR)/resequence_author_table.php
	# Database instance Unique Identifier is requred as part of FossDash setup
	$(INSTALL_DATA) instance_uuid.php $(DESTDIR)$(LIBEXECDIR)/instance_uuid.php
	$(INSTALL_PROGRAM) dbmigrate_pfile_calculate_sha256.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_pfile_calculate_sha256.php
	$(INSTALL_PROGRAM) dbmigrate_change_db_encoding.php $(DESTDIR)$(LIBEXECDIR)/dbmigrate_change_db_encoding.php

	chmod +x $(DESTDIR)$(LIBEXECDIR)/dbmigrate_pfile_calculate_sha256.php

	$(INSTALL_PROGRAM) export_license_ref.php $(DESTDIR)$(LIBEXECDIR)/export_license_ref.php
	$(INSTALL_PROGRAM) dbcreate $(DESTDIR)$(LIBEXECDIR)/dbcreate
	@if [ ! -f "$(DESTDIR)$(INITDIR)/cron.d/fossology" -o "$(OVERWRITE)" ]; then \
		mkdir -p $(DESTDIR)$(INITDIR)/cron.d/; \
		echo "NOTE: using default version for $(DESTDIR)$(INITDIR)/cron.d/fossology"; \
		$(INSTALL_DATA) db.cron $(DESTDIR)$(INITDIR)/cron.d/fossology; \
	else \
		echo "WARNING: $(DESTDIR)$(INITDIR)/cron.d/fossology already exists."; \
		echo "  Not overwriting, consider checking it by hand or use the OVERWRITE option."; \
	fi

uninstall:
	rm -f $(DESTDIR)$(LIBEXECDIR)/fossologyinit.sql
	rm -f $(DESTDIR)$(LIBEXECDIR)/licenseRef.json
	rm -f $(DESTDIR)$(LIBEXECDIR)/clearingProperties.*
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbcreate
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_1.4-2.0.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.0-2.1.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.1-2.2.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.0-2.5-pre.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_2.5-2.6.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_3.3-3.4.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_3.5-3.6.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_3.6-3.7.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_3.7-3.8.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_copyright-event.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_clearing-event.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_real-parent.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_bulk_license.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/export_license_ref.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/fo_mapping_license.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_copyright-author.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_multiple_copyright_decisions.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/resequence_author_table.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_pfile_calculate_sha256.php
	rm -f $(DESTDIR)$(LIBEXECDIR)/dbmigrate_change_db_encoding.php

	@if [ -d $(DESTDIR)$(LIBEXECDIR) ]; then \
		if [ "`ls -A $(DESTDIR)$(LIBEXECDIR)`" ]; then \
			echo "WARNING: $(DESTDIR)$(LIBEXECDIR) not empty, not removing"; \
		else \
			rmdir $(DESTDIR)$(LIBEXECDIR); \
		fi \
	fi

	@echo "WARNING: Cowardly refusing to remove the following"
	@echo "      $(DESTDIR)$(INITDIR)/cron.d/fossology"
	@echo "  Remove by hand if you desire."

clean:
	rm -f core dbcreate db.cron

test: all
	@echo "NOTICE: no tests available for db"

coverage:
	@echo "NOTICE: no coverage available for db"

.PHONY: all install uninstall clean test
