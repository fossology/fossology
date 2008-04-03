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
	@echo "\$$PATH=\"\$$LIBEXECDIR:\$$AGENTDIR:/bin:/usr/bin:/sbin:/usr/sbin\";" >> $(iPATHFILE)
	@echo "\$$VERSION=\"$(VERSION)\";" >> $(iPATHFILE)
	@echo "\$$SVN_REV=\"$(SVN_REV)\";" >> $(iPATHFILE)
	@echo "?>" >> $(iPATHFILE)

InstallationCreate: $(iPATHFILE)
	mkdir -p ../install/$(WEBDIR)
	chmod ug+w ../install/$(WEBDIR)
	tar -cf - `find . -type f | grep -v svn | grep -E "(php|css|htm|html|dtd|htc|gif)$$"` | (cd ../install/$(WEBDIR) ; tar -xvf -)
	touch ../install/$(WEBDIR)/init.ui

install: all InstallationCreate
	if [ -f $(PHPCLI) ] ; then \
	grep -q '^extension.*=.*pgsql.so' $(PHPCLI) || \
	    echo 'extension=pgsql.so' >> $(PHPCLI) ; \
	fi
	if [ -f $(PHPWEB) ] ; then \
	grep -q '^extension.*=.*pgsql.so' $(PHPWEB) || \
	    echo 'extension=pgsql.so' >> $(PHPWEB) ; \
	fi

uninstall:
	(cd $(BINDIR) ; $(RM) $(BINFILES))
	(cd $(WEBPROJECT) ; $(RM) $(WEBFILES))
	(cd $(PHPDIR) ; $(RM) $(LIBFILES))
	(cd $(WEBDIR) ; $(RM) $(WEBFILES))

clean:
	$(RM) $(iPATHFILE)

