# FOSSology UI makefile
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
include ../Makefile.conf

# These are likely to change slowly over time
PHPCLI=/etc/php5/cli/php.ini
PHPWEB=/etc/php5/apache2/php.ini
iPATHFILE=pathinclude.h.php

all: $(iPATHFILE)

$(iPATHFILE): ../Makefile.conf
	@echo "Building $(iPATHFILE)"
	@echo "<?php" > $(iPATHFILE)
	@echo "global \$$GlobalReady;" >> $(iPATHFILE)
	@echo "if (!isset(\$$GlobalReady)) { exit; }" >> $(iPATHFILE)
	@echo "\$$LIBEXECDIR=\"$(LIBEXECDIR)\";" >> $(iPATHFILE)
	@echo "\$$AGENTDIR=\"$(AGENTDIR)\";" >> $(iPATHFILE)
	@echo "\$$SYSCONFDIR=\"$(SYSCONFDIR)\";" >> $(iPATHFILE)
	@echo "\$$WEBDIR=\"$(WEBDIR)\";" >> $(iPATHFILE)
	@echo "\$$PHPDIR=\"$(PHPDIR)\";" >> $(iPATHFILE)
	@echo "\$$VARDATADIR=\"$(VARDATADIR)\";" >> $(iPATHFILE)
	@echo "\$$PROJECT=\"$(PROJECT)\";" >> $(iPATHFILE)
	@echo "\$$DATADIR=\"$(DATADIR)\";" >> $(iPATHFILE)
	@echo "\$$PATH=\"\$$LIBEXECDIR:\$$AGENTDIR:\$$PATH:/usr/bin\";" >> $(iPATHFILE)
	@echo "?>" >> $(iPATHFILE)

InstallationCreate:

install: all InstallationCreate
	if [ -f $(PHPCLI) ] ; then \
	grep -q '^extension.*=.*pgsql.so' $(PHPCLI) || \
	    echo 'extension=pgsql.so' >> $(PHPCLI) ; \
	fi
	if [ -f $(PHPWEB) ] ; then \
	grep -q '^extension.*=.*pgsql.so' $(PHPWEB) || \
	    echo 'extension=pgsql.so' >> $(PHPWEB) ; \
	fi
	# Fix the revision number in about.html
	# sed -e "s/VERSION/$(VERSION)/g" -e "s/SVNREV/$(SVN_REV)/g" about.html > $(WEBDIR)/about.html

uninstall:
	(cd $(BINDIR) ; $(RM) $(BINFILES))
	(cd $(WEBPROJECT) ; $(RM) $(WEBFILES))
	(cd $(PHPDIR) ; $(RM) $(LIBFILES))
	(cd $(WEBDIR) ; $(RM) $(WEBFILES))

clean:
	$(RM) $(iPATHFILE)

