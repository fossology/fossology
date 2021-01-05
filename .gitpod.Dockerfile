FROM gitpod/workspace-full

RUN sudo apt-get update \
 && sudo apt-get install -y \
    lsb-release git build-essential php-xdebug \
    postgresql postgresql-server-dev-all apache2 \
 && echo "IncludeOptional /etc/apache2/sites-enabled/*.conf" | sudo tee -a /etc/apache2/apache2.conf \
 && sudo sed -ie 's/APACHE_RUN_\(.*\)="gitpod"/APACHE_RUN_\1=www-data/' /etc/apache2/envvars
