# FOSSology UI makefile
# Copyright (C) 2007 Hewlett-Packard Development Company, L.P.
include ../Makefile.conf

# These are likely to change slowly over time
PHPCLI=/etc/php5/cli/php.ini
PHPWEB=/etc/php5/apache2/php.ini
LIBFILES=
BINFILES=webgoldimport
WEBFILES=index.php leftnav.php rightframe.php topframe.php common.h.php db_postgres.h.php jobs.h.php webcommon.h.php constants.h.php log.h.php dtree.css dtree.js img dtree del.h.php favicon.ico
iPATHFILE=pathinclude.h.php

all: $(iPATHFILE)

$(iPATHFILE): ../Makefile.conf
	@echo "Building $(iPATHFILE)"
	@echo "<?php" > $(iPATHFILE)
	@echo "\$$LIBEXECDIR=\"$(LIBEXECDIR)\";" >> $(iPATHFILE)
	@echo "\$$AGENTDIR=\"$(AGENTDIR)\";" >> $(iPATHFILE)
	@echo "\$$SYSCONFDIR=\"$(SYSCONFDIR)\";" >> $(iPATHFILE)
	@echo "\$$WEBDIR=\"$(WEBDIR)\";" >> $(iPATHFILE)
	@echo "\$$PHPDIR=\"$(PHPDIR)\";" >> $(iPATHFILE)
	@echo "\$$PROJECTSTATEDIR=\"$(PROJECTSTATEDIR)\";" >> $(iPATHFILE)
	@echo "\$$PROJECT=\"$(PROJECT)\";" >> $(iPATHFILE)
	@echo "\$$DATADIR=\"$(DATADIR)\";" >> $(iPATHFILE)
	@echo "\$$PATH=\"\$$LIBEXECDIR:\$$AGENTDIR:\$$PATH:/usr/bin\";" >> $(iPATHFILE)
	@echo "?>" >> $(iPATHFILE)

InstallationCreate:
	chmod a+x $(BINFILES)
	$(CP) $(BINFILES) ../install/$(AGENTDIR)
	$(CP) -r $(WEBFILES) ../install/$(WEBDIR)
	# remove any SVN files copied by accident
	(cd ../install/$(WEBDIR) ; find . -type d -name \.svn | xargs $(RM) -rf )
#	$(CP) $(LIBFILES) ../install/$(PHPDIR)
	$(CP) *.html *.png ../install/$(WEBDIR)/
	# Fix the revision number in about.html
	sed -e "s/VERSION/$(VERSION)/g" -e "s/SVNREV/$(SVN_REV)/g" about.html > ../install/$(WEBDIR)/about.html
	chmod -R a+r ../install/$(WEBDIR)
	chmod -R a+r ../install/$(PHPDIR)
	# Write the path variables from the master Makefile.conf so that php can source them
	$(CP) $(iPATHFILE) ../install/$(WEBDIR)
	$(CP) $(iPATHFILE) ../install/$(PHPDIR)
	$(CP) $(iPATHFILE) ../install/$(AGENTDIR)

install: all InstallationCreate
	chmod a+x $(BINFILES)
	$(CP) $(BINFILES) $(BINDIR)
	if [ -f $(PHPCLI) ] ; then \
	grep -q '^extension.*=.*pgsql.so' $(PHPCLI) || \
	    echo 'extension=pgsql.so' >> $(PHPCLI) ; \
	fi
	if [ -f $(PHPWEB) ] ; then \
	grep -q '^extension.*=.*pgsql.so' $(PHPWEB) || \
	    echo 'extension=pgsql.so' >> $(PHPWEB) ; \
	fi
	$(MKDIR) -p $(PHPDIR)
#	$(CP) $(LIBFILES) $(PHPDIR)
	$(CP) $(WEBFILES) $(WEBDIR)
	$(CP) $(iPATHFILE) $(WEBDIR)
	$(CP) $(iPATHFILE) $(PHPDIR)
	$(CP) $(iPATHFILE) $(AGENTDIR)
	# Fix the revision number in about.html
	sed -e "s/VERSION/$(VERSION)/g" -e "s/SVNREV/$(SVN_REV)/g" about.html > $(WEBDIR)/about.html

uninstall:
	(cd $(BINDIR) ; $(RM) $(BINFILES))
	(cd $(WEBPROJECT) ; $(RM) $(WEBFILES))
	(cd $(PHPDIR) ; $(RM) $(LIBFILES))
	(cd $(WEBDIR) ; $(RM) $(WEBFILES))

clean:
	$(RM) $(iPATHFILE)

