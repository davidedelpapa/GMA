-- Create MySql User
-- TODO: substitute ***** with yur password
CREATE USER 'gma'@'localhost' IDENTIFIED BY '*****';

-- Create databases
CREATE DATABASE gma;

-- Grant accesses
GRANT ALL ON gma.* TO 'gma'@'localhost';
FLUSH PRIVILEGES;

-- To see privileges:
-- SHOW GRANTS FOR 'gma'@'localhost';
