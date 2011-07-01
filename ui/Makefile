# FOSSology Makefile - ui/
# Copyright (C) 2008 Hewlett-Packard Development Company, L.P.
TOP=..
VARS=$(TOP)/Makefile.conf
include $(VARS)

# also set in Makefile.conf, but we override with a shorter version
iPATHFILE=pathinclude.php

DIRS=images plugins common template plugins/simpleUi locale
FILES=`find . -type f | grep -v svn |grep -v tests | grep -E "(php|css|htm|html|dtd|htc|gif|png|dat|po|mo)$$" |grep -v $(iPATHFILE)`
LINKS=`find .. -type f | grep -v svn | grep -v tests | grep -E "(php)$$" | \
			   grep -v 'simpleUi' | grep -v 'copyright/library.php' | grep -v 'copyright/list.php' \
			   | grep -v 'copyright/view.php' | grep -v 'copyright/agent.php' \
			   | grep -v 'copyright/oneshot.php' | grep -v 'copyright/hist.php' `
RMLINKS=`find . -type l `


all:

$(iPATHFILE): ../Makefile.conf
	@echo "Building $(iPATHFILE)"
	@echo "<?php" > $(iPATHFILE)
	@echo "\$$BINDIR=\"$(BINDIR)\";" >> $(iPATHFILE)
	@echo "\$$LIBDIR=\"$(LIBDIR)\";" >> $(iPATHFILE)
	@echo "\$$LIBEXECDIR=\"$(LIBEXECDIR)\";" >> $(iPATHFILE)
	@echo "\$$INCLUDEDIR=\"$(INCLUDEDIR)\";" >> $(iPATHFILE)
	@echo "\$$MAN1DIR=\"$(MAN1DIR)\";" >> $(iPATHFILE)
	@echo "\$$AGENTDIR=\"$(AGENTDIR)\";" >> $(iPATHFILE)
	@echo "\$$SYSCONFDIR=\"$(SYSCONFDIR)\";" >> $(iPATHFILE)
	@echo "\$$WEBDIR=\"$(WEBDIR)\";" >> $(iPATHFILE)
	@echo "\$$PHPDIR=\"$(PHPDIR)\";" >> $(iPATHFILE)
	@echo "\$$PROJECTSTATEDIR=\"$(PROJECTSTATEDIR)\";" >> $(iPATHFILE)
	@echo "\$$PROJECT=\"$(PROJECT)\";" >> $(iPATHFILE)
	@echo "\$$DATADIR=\"$(DATADIR)\";" >> $(iPATHFILE)
	@echo "\$$VERSION=\"$(VERSION)\";" >> $(iPATHFILE)
	@echo "\$$SVN_REV=\"$(SVN_REV)\";" >> $(iPATHFILE)
	@echo "?>" >> $(iPATHFILE)

install: all $(iPATHFILE)

	for dir in $(DIRS); do \
		mkdir -p $(DESTDIR)$(WEBDIR)/$$dir; \
	done

	for file in $(FILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(WEBDIR)/$$file; \
	done

	$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(PHPDIR)/$(iPATHFILE)
	
	cd $(DESTDIR)$(WEBDIR)/plugins/simpleUi; \
	for link in $(LINKS); do \
	 replink=$${link#../}; \
	 [ -h $${replink} ] && continue; \
		ln -s $$link  $${replink}; \
	done
	
# the src install target is for installing inside of a source tree.  This allows
# developers to use the sourcetree to run the ui from the source tree instead of
# the standard install location.
srcinstall: all $(iPATHFILE)

	cd plugins/simpleUi; \
	for link in $(LINKS); do \
	 replink=$${link#../}; \
	 [ -h $${replink} ] && continue; \
		ln -s $$link  $${replink}; \
	done
		
uninstall:
	for file in $(FILES); do \
		rm -f $(DESTDIR)$(WEBDIR)/$$file; \
	done
	
	$(INSTALL_DATA) $(iPATHFILE) $(DESTDIR)$(PHPDIR)/$(iPATHFILE)
	
	cd $(DESTDIR)$(WEBDIR)/plugins/simpleUi; \
	for link in $(RMLINKS); do \
		rm -f $$link; \
	done
	
	for dir in $(DIRS); do \
		rmdir $(DESTDIR)$(WEBDIR)/$$dir; \
	done

	rm -f $(DESTDIR)$(PHPDIR)/$(iPATHFILE)
	
srcuninstall:

	cd plugins/simpleUi; \
	for link in $(RMLINKS); do \
		rm -f $$link; \
	done

test: all
	@echo "*** Tests are still run by hand for ui/ ***"

coverage:
	@echo "NOTICE: no coverage available for ui"

clean:
	rm -f *.o core $(iPATHFILE)

.PHONY: all install uninstall srcinstall srcuninstall clean test
