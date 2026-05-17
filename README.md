1. Запуск docker-контейнеров: docker-compose up -d
2. Выполнение команды для парсинга файла логов: docker exec -it yii2_php bash -c "php yii parse/parse modimio.access.log" 
(логи парсятся довольно долго, на моем железе заняло около 1 минуты)
3. Зайти на сайт http://localhost:8080/index.php?r=site%2Flogs
