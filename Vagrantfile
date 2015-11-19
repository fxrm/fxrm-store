# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure(2) do |config|
  config.vm.box = "hashicorp/precise32"
  config.vm.provision "shell", inline: <<-END
    echo 'mysql-server-5.5 mysql-server/root_password password root' | debconf-set-selections
    echo 'mysql-server-5.5 mysql-server/root_password_again password root' | debconf-set-selections

    apt-get update -qy
    apt-get install -qy php5 php5-mysql php5-xdebug mysql-server-5.5 curl

    # local composer install
    if [ -f /home/vagrant/composer.phar ]; then
        echo 'Composer already installed'
    else
        echo 'Installing composer'
        curl -sS https://getcomposer.org/installer | php5
    fi
  END
end
