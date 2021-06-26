FROM php:8.0

RUN pecl install xdebug \
	&& docker-php-ext-enable xdebug

WORKDIR /var/www

ENV XDEBUG_SESSION="xdebug" \
	PHP_IDE_CONFIG="serverName=docker"

ENTRYPOINT [ "docker-php-entrypoint" ]
CMD [ "php", "-dxdebug.mode=develop,debug", "-dxdebug.client_host=host.docker.internal", "public/index.php" ]
