@echo off
rem Runs the test suite against the separate db-part-synch-test database
rem (see phpunit.xml). A fresh test database is built from
rem database/schema/mysql-schema.sql, which Laravel loads with the mysql
rem client, so XAMPP's MySQL tools are put on PATH for this run only.

cd /d "%~dp0"
set "PATH=F:\xampp\mysql\bin;%PATH%"
php artisan test %*
