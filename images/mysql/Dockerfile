FROM mysql:8.0

ADD docker-unified.sql /docker-entrypoint-initdb.d

ENV MYSQL_DATABASE=GMA \
    MYSQL_ROOT_PASSWORD=test

EXPOSE 3306