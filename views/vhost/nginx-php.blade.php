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
# Strip cache-buster timestamp (e.g. style.1400749326.css → style.css).
# Only fires when the literal file is missing, so safe across all CMS types.
location ~* "^(.+)\.\d{9,}\.(js|css|png|jpe?g|gif|webp|svg|woff2?|ttf|eot|ico|gzip)$" {
    try_files $uri /$1.$2 =404;
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
