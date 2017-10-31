#!/usr/bin/env python

import time
import sys
import configparser
import redis
import MySQLdb as mdb

# Load the configuration file
ini_conf = configparser.ConfigParser()
ini_conf.read("config.ini")

# ----- SETUP INFO ---------
DBMS_URI = ini_conf['mysql']['host']
DBMS_PORT = int(ini_conf['mysql']['port'])
DBMS_USER = ini_conf['mysql']['user']
DBMS_PASSWD = ini_conf['mysql']['password']
DBMS_DB = ini_conf['mysql']['database']
# --------------------------
REDIS_HOST = ini_conf['redis']['host']
REDIS_PORT = ini_conf['redis']['port']
REDIS_DB = ini_conf['redis']['database']
# --------------------------


# helpers

def error_print(*args, **kwargs):
    print(args, sys.stderr, kwargs)


def logp(msg, level=2):
    if level is 1:
        error_print("[PYWK:ERROR] %s" % str(msg))
    if level is 2:
        error_print("[PYWK:WARNING] %s" % str(msg))
    if level is 3:
        error_print("[PYWK:INFO] %s" % str(msg))

# Functions to run


def redis2mysql(cursor):
    storedCommandsNumber = r.llen('pywk')
    if storedCommandsNumber > 0:
        counter = 0
        while (counter < storedCommandsNumber):
            query = str(r.lpop('pywk'))
            try:
                # check whether it is about sessions
                if '>SessionRecords' in query:
                    # NOTE: SessionRecords queries must be in the form:
                    #       ">SessionRecords {session} {pkey} {ip} {timestamp} {z} {x} {y}"
                    #               [0]         [1]      [2]   [3]     [4]     [5] [6] [7]
                    #
                    # example (redis-cli) RPUSH pywk ">SessionRecords testsession TESTTILE 127.0.0.1 today 13 3456 7890"
                    split_q = query.split()
                    cursor.execute("SELECT id FROM SessionRecords WHERE session = '%s' and pkey = '%s'" % (
                        split_q[1], split_q[2]))
                    result_id = cursor.fetchone()
                    if not result_id:
                        # new session, we need to add it
                        cursor.execute("INSERT INTO SessionRecords (ip, firstaccess, lastaccess, session, pkey) VALUES ('%s', '%s', '%s', '%s', '%s')" % (
                            split_q[3], split_q[4], split_q[4], split_q[1], split_q[2]))
                        session_id = cursor.lastrowid
                    else:
                        # session already exists, just update it
                        session_id = result_id[0]
                        cursor.execute("UPDATE SessionRecords  SET lastaccess = '%s' WHERE id = '%s'" % (
                            split_q[4], session_id))
                    # now we must record the tile info as well...
                    cursor.execute("INSERT INTO TileRecords (sessionid, timestamp, z, x, y) VALUES ('%s', '%s', '%s', '%s', '%s')" % (
                        session_id, split_q[4], split_q[5], split_q[6], split_q[7][:-1]))
                    tile_id = cursor.lastrowid
                else:
                    # Direct query, just execute it. (Un)comment as needed.
                    # cursor.execute(query)
                    pass
            except (mdb.OperationalError, mdb.ProgrammingError) as e:
                # logp("[Redis:Query] %s [MySql:ERROR-YELD] %s" %
                #     (str(query), str(e.args)))
                print(query)
                print(e.args)
                print(cursor._last_executed)
            # increment counter
            counter = counter + 1


# Setup
r = redis.Redis(REDIS_HOST, REDIS_PORT, REDIS_DB)
try:
    connection = mdb.connect(
        DBMS_URI, DBMS_USER, DBMS_PASSWD, DBMS_DB, DBMS_PORT)
except (mdb.OperationalError, mdb.ProgrammingError) as e:
    logp("[MySQL:Init] [ERROR-YELD] %s" % (str(e.args)))

with connection:
    cursor = connection.cursor()
    # Cycle
    while True:
        redis2mysql(cursor)
        connection.commit()
        time.sleep(5)  # Sleeps for 5 seconds
