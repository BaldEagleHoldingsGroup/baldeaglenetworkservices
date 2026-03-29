# Ops Notes

## Website form storage path

Production requires:

- /var/www/network/public_html/storage
- owner/group writable for Apache runtime
- SELinux label: httpd_sys_rw_content_t

Commands used on production:

sudo mkdir -p /var/www/network/public_html/storage
sudo chown apache:apache /var/www/network/public_html/storage
sudo chmod 775 /var/www/network/public_html/storage
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/network/public_html/storage(/.*)?'
sudo restorecon -Rv /var/www/network/public_html/storage
