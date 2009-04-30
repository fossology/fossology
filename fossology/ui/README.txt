Copyright (C) 2008 Hewlett-Packard Development Company, L.P.

This is the proposed new UI.
It is a work in progress and intentionally NOT included by make-install.
(In fact, I don't even think the install part of Makefile works yet...)

I am checking in the code so I don't accidentally lose it and to allow
a few other folks to work in it and make comments.
(Yes: I am writing it for PHP4 and not PHP5.  But it should work with PHP5.)

If you have any comments or feedback, please let me know: Neal Krawetz


To use:
  - Run "make".  This will create the path_include php file.
  - Create a symbolic link from a web-accessible directory to this
    directory, and test away.

