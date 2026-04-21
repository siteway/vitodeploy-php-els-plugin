if ! sudo DEBIAN_FRONTEND=noninteractive apt-get install -y alt-php{{ $version }}; then
    echo 'VITO_SSH_ERROR' && exit 1
fi

# Install MySQL extensions
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y alt-php{{ $version }}-mysql80 alt-php{{ $version }}-mysqlnd alt-php{{ $version }}-mbstring alt-php{{ $version }}-gd alt-php{{ $version }}-xml alt-php{{ $version }}-opcache

# Enable all available modules
PHP_ETC="/opt/alt/php{{ $version }}/etc"
if [ -d "$PHP_ETC/php.d.all" ]; then
    sudo cp "$PHP_ETC/php.d.all/"*.ini "$PHP_ETC/php.d/" 2>/dev/null
fi

# Comment out duplicate dom.so in xmlreader.ini and xsl.ini (already loaded by dom.ini)
if [ -f "$PHP_ETC/php.d/dom.ini" ]; then
    for _ini in "$PHP_ETC/php.d/xmlreader.ini" "$PHP_ETC/php.d/xsl.ini"; do
        if [ -f "$_ini" ]; then
            sudo sed -i 's|^extension=dom\.so|; extension=dom.so|' "$_ini"
        fi
    done
fi

# Create FPM pool config if none exists
POOL_DIR="/opt/alt/php{{ $version }}/etc/php-fpm.d"
if [ -z "$(ls -A $POOL_DIR/*.conf 2>/dev/null)" ]; then
    sudo mkdir -p "$POOL_DIR"
    sudo bash -c "cat > $POOL_DIR/www.conf" <<'POOLEOF'
[www]
user = {{ $user }}
group = {{ $user }}

listen = /run/alt-php{{ $version }}-fpm/php-fpm.sock
listen.owner = vito
listen.group = vito
listen.mode = 0660

pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
POOLEOF
fi

# Ensure socket directory exists and survives reboots via tmpfiles.d
echo "d /run/alt-php{{ $version }}-fpm 0755 root root -" | sudo tee /etc/tmpfiles.d/alt-php{{ $version }}-fpm.conf > /dev/null
sudo mkdir -p /run/alt-php{{ $version }}-fpm

sudo systemctl enable alt-php{{ $version }}-fpm
sudo systemctl start alt-php{{ $version }}-fpm
