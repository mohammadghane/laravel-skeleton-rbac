#!/bin/bash

DATE=`date +%Y%m%d_%H%M`
LOCAL_BACKUP_DIR="/var/www/laravel/bootstrap/backup"
DB_NAME="overtime"
DB_USER="root"
DB_PASSWORD="P@ssw0rd"
FTP_SERVER="127.0.0.1"
FTP_USERNAME="ftpuser"
FTP_PASSWORD="1921685045"
FTP_UPLOAD_DIR="/"
FILE="DATABASE_BACKUP_$DATE"

mkdir -p $LOCAL_BACKUP_DIR
mysqldump -u $DB_USER  -p$DB_PASSWORD $DB_NAME | gzip  > $LOCAL_BACKUP_DIR/$FILE.sql.gz

zip --password  $FILE $LOCAL_BACKUP_DIR/$FILE.zip $LOCAL_BACKUP_DIR/$FILE.sql.gz

rm -rf $LOCAL_BACKUP_DIR/$FILE.sql.gz

echo "Remove old  files."

# ftp -n $FTP_SERVER << EndFTP
# user "$FTP_USERNAME" "$FTP_PASSWORD"
# binary
# hash
# cd $FTP_UPLOAD_DIR
#pwd
# lcd $LOCAL_BACKUP_DIR
# put "$FILE.zip"
# bye
# EndFTP
