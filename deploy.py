import paramiko

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('77.83.203.179', username='root', password='plI-2mTmKCA', timeout=30)

for cmd in [
    'cd /var/www/kankio && git pull origin main',
    'cd /var/www/kankio && php artisan cache:clear && php artisan view:clear',
]:
    _, o, e = c.exec_command(cmd)
    print(o.read().decode() or e.read().decode())

c.close()
print('Deploy tamam!')
