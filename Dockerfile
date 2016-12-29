FROM php:cli
MAINTAINER Stefan Schallenberg <infos (at) nafets.de>
LABEL Description="CardDAV contacts import for AVM FRITZ!Box"

# prepare image directories
RUN mkdir -p /usr/src/carddav2fb /var/lib/carddav2fb /etc/carddav2fb
VOLUME /var/lib/carddav2fb/ /etc/carddav2fb
WORKDIR /usr/src/carddav2fb
CMD [ "php", "./carddav2fb.php", "/etc/carddav2fb/config.php" ]

# copy source code
COPY lib /usr/src/carddav2fb/lib
COPY config.example.php /usr/src/carddav2fb
COPY carddav2fb.php /usr/src/carddav2fb

