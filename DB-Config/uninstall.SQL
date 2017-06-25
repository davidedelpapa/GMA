-- Obviously only to wipe out EVERYTHING!

USE gma;

DROP TABLE `pkeys`;
DROP TABLE `Users`;

DROP DATABASE gma;

-- as root
DROP USER 'gma'@'localhost';
