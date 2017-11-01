#!/bin/bash

# This script gives me the possibility to add hooks for the 
# containers' building process, as well as the possibility to add
# other hooks to other commands later on.
# Not implemented docker-compose commands can be run from docker-compose itself.
#
# Added also a useful shorthand for getting any container's prompt
# and to get a mysql and a redis shell from the respective containers while running.
#
# Note: very simplistic approach. No test, no exeception handled, 
# very open to hacks of whatever kind. 
# It does not even test if docker-compose is running at all.

# Routines for .ini files. !DANGER! Use at your own risk!
get_conf_()
{
    result=($(grep -n "$2" $1 | while read -r line; do echo $line | tr ' ' '\n' | tail -1; done))
}
get_conf()
{
    get_conf_ $1 $3
    echo ${result[${2:-0}]}
}
INI=config.ini
INI_MYSQL=0
INI_REDIS=1
MYSQL_HOST=$(get_conf $INI $INI_MYSQL host)
MYSQL_PORT=$(get_conf $INI $INI_MYSQL port)
MYSQL_DATABASE=$(get_conf $INI $INI_MYSQL database)
MYSQL_USER=$(get_conf $INI $INI_MYSQL user)
MYSQL_PASSWD=$(get_conf $INI $INI_MYSQL password)
REDIS_HOST=$(get_conf $INI $INI_REDIS host)
REDIS_PORT=$(get_conf $INI $INI_REDIS port)


usage()
{
    echo -e "\nUsage:\n"
    echo -e "build\t(Re)builds the images."
    echo -e "start\tStarts the services."
    echo -e "stop \tStops the running containers."
    echo -e "\n--------- Stats -----------"
    echo -e "ps \t\t\tShows running containers and some stats."
    echo -e "stats [container-name]\tShows running container(s) and some stats."
    echo -e "\t\t\tNote: Stats in real time; <Ctrl+C> to stop visualizing."
    echo -e "\n------- Get Shells --------"
    echo -e "sh           <image-name>    \tGet a shell from a container image."
    echo -e "hot-sh | hsh <container-name>\tGet a shell from a running image."
    echo -e "\n--- Get Services Shells ---"
    echo -e "mysql \tGet a mysql shell from the mysql container."
    echo -e "redis \tGet a redis shell from the redis container."
    echo -e "\n--------- Other -----------"
    echo -e "-h --help \tPrints this help guide."
}

build()
{
    # Because of a bug when running GTS on the same machine, 
    # there's a patched config.ini in place in images/python/
    cp requirements.txt worker.py ./images/python/
    docker-compose build
    rm -f ./images/python/requirements.txt ./images/python/worker.py
}

start()
{
    docker-compose up -d
}

stop()
{
    docker-compose stop
}

ps()
{
    docker-compose ps
}

get_running_containers_()
{
    result=($(docker-compose ps -q | while read -r line; do echo $line; done))
}
get_running_containers()
{
    get_running_containers_
    echo ${result[*]}
}

stats()
{
    containers=$(get_running_containers)
    docker stats --format "table {{.Name}}\t{{.CPUPerc}}  {{.MemPerc}}  {{.MemUsage}}\t{{.NetIO}}" ${1:-$containers}
}

sh()
{
    docker run -it $1 /bin/bash
}

hsh()
{
    docker exec -it $1 bash
}

mysql()
{
    exec mysql -h$MYSQL_HOST --port $MYSQL_PORT -D$MYSQL_DATABASE -u$MYSQL_USER -p$MYSQL_PASSWD
}

redis()
{
    exec redis-cli -h $REDIS_HOST -p $REDIS_PORT
}

while [ "$1" != "" ]; do
    case $1 in
        start )                 start
                                exit
                                ;;
        stop )                  stop
                                exit;;
        build )                 build
                                exit
                                ;;
        ps )                    ps
                                exit
                                ;;
        stats )                 stats ${2:-""}
                                exit
                                ;;
        sh )                    sh $2
                                exit
                                ;;
        hot-sh | hsh )          hsh $2
                                exit
                                ;;
        mysql )                 mysql
                                exit
                                ;;
        redis )                 redis
                                exit
                                ;;
        -h | --help )           usage
                                exit
                                ;;
        * )                     usage
                                exit
    esac
    shift
done
usage