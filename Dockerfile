FROM cloudron/php-base:8.4@sha256:365607342e6b50f4f53d9b524313df4bfe654596baf762ee2e5af58c3498aa4b

LABEL org.opencontainers.image.source="https://github.com/ananda-bhatta/itop-cloudron" \
      org.opencontainers.image.description="Community-maintained iTop package for Cloudron" \
      org.opencontainers.image.licenses="AGPL-3.0-or-later"

ARG ITOP_VERSION=3.3.0
ARG ITOP_BUILD=21411
ARG ITOP_SHA256=b4e52f8d5da53d990630a11dbda943de110cb9df6fea8228e382443975abc0e4

RUN rm -f /etc/apt/sources.list.d/ondrej-ubuntu-php-noble.sources \
    && apt-get -o Acquire::Retries=5 update \
    && apt-get -o Acquire::Retries=5 install -y --no-install-recommends graphviz \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app/code
RUN curl -fL --retry 3 "https://github.com/Combodo/iTop/releases/download/${ITOP_VERSION}/iTop-${ITOP_VERSION}-${ITOP_BUILD}.zip" -o /tmp/itop.zip \
    && echo "${ITOP_SHA256}  /tmp/itop.zip" | sha256sum -c - \
    && unzip -q /tmp/itop.zip -d /tmp/itop-release \
    && mv /tmp/itop-release/web /app/code/upstream \
    && cp /tmp/itop-release/LICENSE /app/code/ITOP-LICENSE \
    && printf '%s\n' "${ITOP_VERSION}" > /app/code/upstream-version \
    && rm -rf /tmp/itop.zip /tmp/itop-release

COPY scripts/patch-itop.php /app/code/patch-itop.php
COPY cloudron-settings.php health.php bootstrap.php finish-bootstrap.php /app/code/
RUN php8.4 /app/code/patch-itop.php /app/code/upstream/core/config.class.inc.php

RUN a2dissite 000-default && a2dismod mpm_event
RUN a2enmod mpm_prefork php8.4 rewrite headers auth_basic authn_file \
    && printf 'Listen 8000\n' > /etc/apache2/ports.conf \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/cloudron.conf \
    && a2enconf cloudron
COPY apache.conf /etc/apache2/sites-available/itop.conf
COPY php.ini /etc/php/8.4/apache2/conf.d/99-itop.ini
COPY php.ini /etc/php/8.4/cli/conf.d/99-itop.ini
RUN a2ensite itop
COPY start.sh cron.sh /app/code/
RUN chmod 755 /app/code/start.sh /app/code/cron.sh
EXPOSE 8000
CMD ["/app/code/start.sh"]
