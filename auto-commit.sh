#!/bin/sh

now=$(date +"%T")

git status
#git checkout master
git add .
git commit -m "Auto-Commit: Added at $now"
git push
git status

# rsync -vahd --stats --exclude=/files --exclude=/config.php --exclude=.git/ --exclude=/editor/public/files --exclude=/editor/database --progress /var/www/html/master/ /var/www/html/gordon