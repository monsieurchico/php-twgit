#!/usr/bin/env sh

git checkout master
git pull --rebase
cp -R deploy /tmp 
git checkout gh-pages 
git pull --rebase 
cp -R /tmp/deploy . 
git commit -am "$(cat ./deploy/REVISION.md)" 
git push origin HEAD
git checkout master
