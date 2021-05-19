#!/bin/bash

REPO_PATH=$1
BRANCH=$2

cd $REPO_PATH
git fetch
git reset --hard
git checkout $BRANCH
git pull

composer update
php artisan migrate
