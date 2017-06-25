# GMA
WebMap Analytics

## Introduction

Web traffic analysis is already an established field in computer science (maybe...), yet, if you wish to apply it in the web GIS field, you would find yourself disappointed. In fact, while it is easy to check the access to a page, traffic analytics, when applied to a web map, faces a different set of metrics to analyze: there are images and their geographic reference.

- Images: web map tiles, are in a most simplified way, just images, commonly a .jpg set of 256 * 256 images, present in a "tile tree" which is a directories structure. Both directories and images are given numbers, and one has to calculate what they refer to, which leads us to the second point...
- Geographic reference: what a web map analytics should do (at least) is not only understand the concept of actions as reffed to maps (e.g., zoom, pan, ...), but also which parts of the map the users are more interested to, which of course refer to real geographical places, i.e., get a set of most visited coordinates.

GMA tries to answer these basic needs with an open source PHP service, able to be integrated with already existing web map tools.

## Status of the Project

The project started up as an internal effort to produce a tile server for [Sentinelmap.eu](https://sentinelmap.eu). After more than 6 months of active development the Sentinelmap team concluded it would be best to separate the tile server from the traffic analysis tool. The tile server, [GTS](https://github.com/oldmammuth/GTS), was released first as of june 1st, 2017. It is now the turn of the GMA. The original project reached a status of working prototype. However, the codebase developed till the splitting of the project has to be checked and in parts rewritten to adapt to a standalone life. Therefore to-date the project is still in an incomplete form. Check out here soon, and you might find the project already completed and up for running.

**Note**: tests done on my machine show that the backend works already like a charm; however, I did not yet port the analytics compiler, and UI, so that at the moment the system is just storing information on the single tile, and nothing more. Moreover, I didn't adapt for georeferencing of the tiles as yet.

## Requirements

GMA relies on MySql/MariaDB and Redis, as well as using PHP and a Python worker.

PHP requirements are covered by

- mysqli
- predis

I advice to use your distro's own package installer for these;
for example:

```shell
sudo apt-get install php-mysql php7-redis
```

(note: the above is just an example, which would normally work on Debian-based distros)

All python requirements are found in *requirements.txt*; you can install the as usual with:

```shell
sudo pip install -r requirements.txt 
```

However, without the development packages for mysql the pip installation is deemed to fail. If you wish to install them using your distro's own package manager, feel free to do so.
For example:

```shell
sudo apt-get install python-pip python-dev libmysqlclient-dev
```

After which you can safely install:

```shell
sudo pip install -r requirements.txt 
```

## Configuration

Take a look at *config.ini*, and modify accordingly

In order to install the whole thing, use the SQL scripts found inside *DB-Config/*.
Notice that you must specify a password for the user gma, in the script *01_setup_databases.SQL*

Once the requirements are met, just copying the files *gma.php*, *config.ini*, and *.htaccess* in the root folder of your server should work;
to start the python worker a simple command like the following should suffice
```shell
python worker.py &
```
However, in order for it to run in the background you should create a .service file with the command and put in your systemd services script directory.

In order to test the whole thing, take a look, and modify, *test.html*

## Note on the worker.py

The worker is tailored to recognize an internal command and transform it in a SQL query, lastly performing it on the mysql connection.
However, the system is open to receive any sql command and execute them directly: it just works as a sql query queue using Redis. You could just use it for that purpose, however keep in mind that **so far the system is not secure!**
