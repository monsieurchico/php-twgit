#!/usr/env/bin sh

lastTag=$(git tag -l --sort=-version:refname "*" | head -n 1)
revision=$(git log --format=format:%H | awk '{print substr($1, 1, 7)}' | head -n 1)
version=$(echo $lastTag"-"$revision)

if [ ! -f composer.phar ]; then
    echo "Download composer"
    $(which php) -r "readfile('https://getcomposer.org/installer');" | php
fi

if [ ! -f box.phar ]; then
    echo "Download box..."
    curl -LSs https://box-project.github.io/box2/installer.php | php
fi

echo "Prepare deploy directory..."
if [ -d deploy ]; then
    rm -rf deploy/*
else
    mkdir deploy 2> /dev/null
fi

echo "Prepare build directory..."
if [ -d build ]; then
    rm -rf build/*
else
    mkdir build 2> /dev/null
fi

cp -R * build/ 2> /dev/null

echo "Changedir build"
cd build

if [ ! -z "$version " ]; then
    echo "Set version $version..."
    sed -ie "s/twgit_revision/$version/g" src/NMR/Application.php
fi

echo "Composer install..."
$(which php) composer.phar install -vv --no-dev
rm -f composer.phar

echo "Clean useless files..."
cd vendor && find . | grep -i "Test" | xargs rm -rf
cd ../vendor && find . | grep -E "\.md$" | xargs rm -rf
cd ../vendor && find . | grep -E "\.dist$" | xargs rm -rf
cd ../vendor && find . | grep -E "\.json$" | xargs rm -rf
cd ../vendor && find . | grep -E "LICENSE$" | xargs rm -rf
cd ../vendor && find . | grep -E "Makefile$" | xargs rm -rf
cd ../

echo "Build phar..."
$(which php) box.phar build -v
rm -f box.phar

echo "Deploy phar..."
mv *.phar ../deploy
echo "$version" >> ../deploy/REVISION.md

echo "Clean build..."
cd ..
rm -rf build

echo ""
echo "(i) To install twgit: "
if [ -f /usr/local/bin/twgit ]; then
    echo "$> rm -f /usr/local/bin/twgit"
fi
echo "$> ln -sfv $(pwd)/deploy/twgit.phar /usr/local/bin/twgit"