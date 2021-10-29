start() {
    echo "并发执行多个 PHP 脚本..."
    for ((i = 0;i < 25; i++))
    do
        nohup php /home/work/www/php-net/client.php 2>&1 &
    done
}

start