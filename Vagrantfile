# -*- mode: ruby -*-
# vi: set ft=ruby :

# SPDX-FileCopyrightText: © 2014,2022, Siemens AG

# SPDX-License-Identifier: GPL-2.0-only AND LGPL-2.1-only
$post_up_message = <<WELCOME
Use your FOSSology at http://localhost:8081/repo/
  Default user: fossy (see documentation for password)

Or do a 'vagrant ssh' and look at '/fossology' for your source tree.

Prepare development environment and run tests via:
$ vagrant ssh
$ cd /fossology
$ ./utils/prepare-vagrant-dev.sh
$ PGHOST=localhost make test
WELCOME

$add_proxy_settings = <<PROXYSCRIPT
# get gateway of running VM and set proxy info within global profile
echo 'export host_ip="`netstat -rn | grep "^0.0.0.0 " | cut -d " " -f10`"'  > /etc/profile.d/proxy.sh
echo 'export ftp_proxy="http://$host_ip:3128"'                             >> /etc/profile.d/proxy.sh
echo 'export http_proxy="http://$host_ip:3128"'                            >> /etc/profile.d/proxy.sh
echo 'export https_proxy="https://$host_ip:3128"'                          >> /etc/profile.d/proxy.sh

# load the proxy within current shell
. /etc/profile.d/proxy.sh

if ! sudo grep -q http_proxy /etc/sudoers; then
  sudo su -c 'sed -i "s/env_reset/env_reset\\nDefaults\\tenv_keep = \\"http_proxy https_proxy ftp_proxy\\"/" /etc/sudoers'
fi
PROXYSCRIPT

$build_and_test = <<SCRIPT
set -o errexit

echo "Provisioning system to compile, test and develop."
sudo DEBIAN_FRONTEND=noninteractive apt-get update -qq -y

date > /etc/vagrant.provisioned

echo "lets go!"
cd /fossology

DEBIAN_FRONTEND=noninteractive ./utils/fo-installdeps -y

sudo cmake -DCMAKE_BUILD_TYPE=Release -S. -B./build -G Ninja 
sudo cmake --build ./build --parallel 
sudo ninja -C ./build install

sudo /usr/local/lib/fossology/fo-postinstall

sudo cp /fossology/install/src-install-apache-example.conf /etc/apache2/conf-available/fossology.conf
sudo a2enconf fossology.conf

# increase upload size
sudo /fossology/install/scripts/php-conf-fix.sh --overwrite

sudo /etc/init.d/apache2 restart

sudo systemctl daemon-reload
SCRIPT

Vagrant.configure("2") do |config|
  config.vm.box = "ubuntu/focal64"
  config.vm.post_up_message = $post_up_message
  config.vm.synced_folder ".", "/vagrant", disabled: true
  config.vm.synced_folder ".", "/fossology"

  config.vm.provider "virtualbox" do |vbox|
    vbox.customize ["modifyvm", :id, "--memory", "4096"]
    vbox.customize ["modifyvm", :id, "--cpus", "2"]
  end

  config.vm.network "forwarded_port", guest: 80, host: 8081
  config.vm.network "forwarded_port", guest: 5432, host: 5432

  # use proxy from host if environment variable PROXY=true
  if ENV['PROXY']
    if ENV['PROXY'] == 'true'
      config.vm.provision "shell" do |shell|
        shell.inline = $add_proxy_settings
      end
    end
  end

  # call the script
  config.vm.provision "shell" do |shell|
    shell.inline = $build_and_test
  end

  config.vm.provision "shell", run: "always" do |shell|
    shell.inline = "/etc/init.d/fossology start"
  end
end
