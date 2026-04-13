#[php]
@php
    $phpSocket = "unix:/run/alt-php{$version}-fpm/php-fpm.sock";
    if ($site->isIsolated()) {
        $phpSocket = "unix:/run/alt-php{$version}-fpm/php-fpm-{$site->user}.sock";
    }
@endphp
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    fastcgi_pass {{ $phpSocket }};
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
    fastcgi_hide_header X-Powered-By;
}
# Serve TYPO3 pre-compressed .gzip assets with correct MIME types
location ~* \.css\.gzip$ {
    add_header Content-Encoding gzip;
    default_type text/css;
}
location ~* \.js\.gzip$ {
    add_header Content-Encoding gzip;
    default_type application/javascript;
}

# CORS for web fonts
location ~* \.(eot|otf|ttc|ttf|woff2?)$ {
    add_header Access-Control-Allow-Origin "*";
}

index index.php index.html;
error_page 404 /index.php;
#[/php]
