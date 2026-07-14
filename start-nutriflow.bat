@echo off
cd /d "%~dp0"
php -d extension_dir="D:\PhP\ext" -d extension=mbstring -d extension=pdo_sqlite -d extension=sqlite3 -d extension=fileinfo -d extension=openssl -S 127.0.0.1:8001 -t public public\index.php
