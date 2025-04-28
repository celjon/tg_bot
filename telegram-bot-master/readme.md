# Бот для общения с ChatGPT через Bothub API в Telegram

### Первичное разворачивание
Все команды выполняются из корня проекта:

- Создать файл с настройками проекта, выполнить команду из корня проекта `cp .env .env.local`
- Добавить настройки подключения к БД в параметр `DATABASE_URL` в файл .env.local, предварительно на сервере должен быть установлен PostgreSQL
- Прописать в .env.local `APP_ENV=prod`
- Прописать в .env.local параметры `TELEGRAM_TOKEN`, `BOTHUB_API_URL` и `BOTHUB_SECRET_KEY`
- Настроить веб-сервер на папку `public`
- Установить необходимые библиотеки: `composer install`
- Создать БД: `php bin/console doctrine:database:create`
- Применить миграции: `php bin/console doctrine:migrations:migrate`
- Сбросить кэш: `php bin/console cache:clear`
- Установить вебхук для Telegram: `php bin/console set-tg-webhook https://{ДОМЕН_БОТА}/tg-webhook`
- В отдельном скрине запустить команду `php bin/cycle.php`

### Выполнять при каждом деплое

- Установить необходимые библиотеки: `composer install`
- Применить миграции: `php bin/console doctrine:migrations:migrate`
- Сбросить кэш: `php bin/console cache:clear`

### Локальный запуск (без вебхука)
Для локального тестирования проще и быстрее использовать Long Polling:

- Отвязать текущий вебхук: `php bin/console set-tg-webhook ""`
- Запустить long polling для получения обновлений бота: `php bin/polling.php`
- Запустить в отдельном терминале воркера, для обработки считанных команд: `php bin/cycle.php`