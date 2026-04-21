sudo systemctl stop alt-php{{ $version }}-fpm 2>/dev/null
sudo systemctl disable alt-php{{ $version }}-fpm 2>/dev/null

if ! sudo DEBIAN_FRONTEND=noninteractive apt-get remove -y alt-php{{ $version }}; then
    echo 'VITO_SSH_ERROR' && exit 1
fi

if [ -f /etc/tmpfiles.d/alt-php{{ $version }}-fpm.conf ]; then
    sudo rm -f /etc/tmpfiles.d/alt-php{{ $version }}-fpm.conf
fi
