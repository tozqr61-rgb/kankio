import paramiko

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('77.83.203.179', username='root', password='plI-2mTmKCA', timeout=30)

cmds = [
    # Check if route exists
    "cd /var/www/kankio && php artisan route:list --path=admin/users 2>&1 | grep role",
    # Check actual user roles in DB
    "mysql -ukankio -parJ7VCzUshYeAT3iuRzb kankio -e 'SELECT id, username, role FROM users;' 2>&1",
    # Tail the log for recent errors
    "tail -30 /var/www/kankio/storage/logs/laravel.log 2>&1 | grep -E 'ERROR|Exception|role|admin' | tail -10",
]

for cmd in cmds:
    _, o, e = c.exec_command(cmd)
    out = o.read().decode()
    err = e.read().decode()
    print(f'>> {cmd[:70]}')
    print(out if out else err)

c.close()
