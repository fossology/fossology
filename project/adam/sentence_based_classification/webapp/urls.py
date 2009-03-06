from django.conf.urls.defaults import *
import views

# Uncomment the next two lines to enable the admin:
# from django.contrib import admin
# admin.autodiscover()

urlpatterns = patterns('',
    # Example:

    (r'^$', views.index),
    (r'^post/$', views.post),
    (r'^print/$', views.print_file),
    (r'^default.xsl$', views.xml_style_sheet),
    (r'^print/default.xsl$', views.xml_style_sheet),

    # Uncomment the admin/doc line below and add 'django.contrib.admindocs' 
    # to INSTALLED_APPS to enable admin documentation:
    # (r'^admin/doc/', include('django.contrib.admindocs.urls')),

    # Uncomment the next line to enable the admin:
    # (r'^admin/(.*)', admin.site.root),
)
