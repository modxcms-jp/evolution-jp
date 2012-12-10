# http://modx.jp/docs/admin/htaccess.html

# Options +FollowSymlinks
RewriteEngine On
RewriteBase /
# MODXをサブディレクトリにインストールしている場合は「/modx」などに。

RewriteRule ^(manager|assets)/.*$ - [L]
RewriteRule \.(jpg|jpeg|png|gif|ico)$ - [L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME}/index.html !-f
RewriteCond %{REQUEST_FILENAME}/index.php !-f
RewriteRule . index.php [L]
