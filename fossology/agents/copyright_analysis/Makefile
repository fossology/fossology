# pull in all our default variables
include Makefile.conf

all: model.dat copyright

copyright: copyright.py
	cat copyright.py > copyright
	echo -n "    sys.path.append('" >> copyright
	echo -n $(DESTDIR)$(LIBDIR) >> copyright
	echo -e "/python_fossology/')\n    import libfosspython\n    import copyright_library as library\n    main()\n" >> copyright
	chmod a+x copyright

model.dat: copyright training_files
	./copyright --model model.dat --training training_files
	
training_files:
	find data -type f > training_files

install: all
	./copyright --setup
	$(INSTALL_PROGRAM) copyright $(DESTDIR)$(AGENTDIR)/copyright
	mkdir -p $(DESTDIR)$(LIBDIR)/python_fossology/
	$(INSTALL_PROGRAM) copyright_library.py $(DESTDIR)$(LIBDIR)/python_fossology/copyright_library.py
	$(INSTALL_PROGRAM) model.dat $(DATADIR)/model.dat
	make -C ui-plugin install

uninstall: 
	rm -rf $(DESTDIR)$(AGENTDIR)/copyright
	rm -rf $(DESTDIR)$(LIBDIR)/python_fossology/copyright_library.py
	rmdir $(DESTDIR)$(LIBDIR)/python_fossology/
	rm -rf $(DATADIR)/model.dat
	make -C ui-plugin uninstall

clean:
	rm copyright model.dat training_files
