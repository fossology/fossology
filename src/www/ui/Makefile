# SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.

# SPDX-License-Identifier: GPL-2.0-only

TOP = ../../..
VARS = $(TOP)/Makefile.conf
include $(VARS)

CONFDIR = $(DESTDIR)$(SYSCONFDIR)

DIRS = css images locale scripts ajaxPHP

UIFILES = `find . -type f | grep -v svn |grep -v tests | grep -E "(php|dat|dtd|js|html|twig)$$"`
OBSOLETEFILES = admin-tag-ns.php admin-change-owner.php admin-tag-ns-perm.php admin-folder-delete.php \
                admin-dashboard.php ajax-showjobs.php group-manage-self.php group-manage.php ajax-perms.php \
                upload_permissions.php upload-srv-files.php upload-vcs.php template/components \
                template/include ui-browse-license.php ui-license-list.php \
                template/ui-license-list-form.html.twig

OTHERFILES = `find . -type f | grep -v svn |grep -v tests | grep -E "(css|htc|gif|png|ico|htm|openapi.yaml)$$"`

TPL = $(TOP)/NOTICES-THIRDPARTY.html

RMFILES = FILES = `find . -type f | grep -v svn |grep -v tests | grep -E "(php|css|htm|html|dtd|htc|gif|png|dat|po|mo)$$"`

all:
#	php5 fo-render.php m

install: empty-cache
	@mkdir -p $(DESTDIR)$(WEBDIR)/ui
	@for dir in $(DIRS); do \
		mkdir -p $(DESTDIR)$(WEBDIR)/ui/$$dir; \
	done
	@for file in $(OBSOLETEFILES); do \
		rm -rf $(DESTDIR)$(WEBDIR)/ui/$$file; \
		rm -rf $$file; \
	done
	@for file in $(UIFILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(WEBDIR)/ui/$$file; \
	done
	@for file in $(OTHERFILES); do \
		$(INSTALL_DATA) $$file $(DESTDIR)$(WEBDIR)/ui/$$file; \
	done
	@echo $(SYSCONFDIR) > $(DESTDIR)$(WEBDIR)/ui/fossology.rc
	@mkdir -p $(CONFDIR)/mods-enabled
	@if test ! -e $(CONFDIR)/mods-enabled/www; then \
		ln -s $(MODDIR)/www $(CONFDIR)/mods-enabled; \
	fi
	@ln -sfn $(MODDIR)/www/ui/page $(CONFDIR)/mods-enabled/www-page
	@ln -sfn $(MODDIR)/www/ui/async $(CONFDIR)/mods-enabled/www-async
	@$(INSTALL_DATA) $(TPL) $(DESTDIR)$(WEBDIR)/ui/NOTICES-THIRDPARTY.html

empty-cache:
	rm -rf $(CACHEDIR)/*

uninstall:
	rm -rf $(DESTDIR)$(WEBDIR)
	rm -f $(CONFDIR)/mods-enabled/www

clean:
	rm -f *.o core $(iPATHFILE)

.PHONY: all install uninstall clean
