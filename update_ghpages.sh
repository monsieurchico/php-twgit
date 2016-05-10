#!/usr/bin/env sh

git checkout master
if [ $? -ne 0 ]; then
    exit -1;
fi
git pull --rebase
if [ $? -ne 0 ]; then
    exit -1;
fi

sh makefile.sh

cp -R deploy /tmp
if [ $? -ne 0 ]; then
    exit -1;
fi
git checkout gh-pages 
if [ $? -ne 0 ]; then
    exit -1;
fi
git pull --rebase 
if [ $? -ne 0 ]; then
    exit -1;
fi
cp -R /tmp/deploy . 
if [ $? -ne 0 ]; then
    exit -1;
fi
git commit -am "$(cat ./deploy/REVISION.md)" 
if [ $? -ne 0 ]; then
    exit -1;
fi
git push origin HEAD
if [ $? -ne 0 ]; then
    exit -1;
fi
git checkout master
