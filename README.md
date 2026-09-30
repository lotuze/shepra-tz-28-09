# Импорт товаров

Full-stack-приложение для импорта товаров из XLSX.

Backend построен на Slim 4 и Doctrine ORM и предоставляет API с JWT-авторизацией. Symfony Messenger передаёт задачи импорта через RabbitMQ. Frontend реализован на Angular 20 с использованием standalone-компонентов и NgRx для управления списком товаров.

PostgreSQL хранит товары, состояние импорта, пользователей и ограничения частоты запуска импорта.

## Быстрый запуск

```bash
git clone git@github.com:lotuze/shepra-tz-28-09.git
cd shepra
cp .env.example .env
make build
make up
make migrate
make fixtures
```

После запуска доступны:

- Frontend: http://localhost:4200
- Проверка backend: http://localhost:8080/api/health
- RabbitMQ Management: http://localhost:15672

Данные демонстрационного пользователя:

```text
Email: admin@example.com
Пароль: ChangeMe123!
```

Пользователь предназначен только для локальной разработки и демонстрации.

Полезные команды:

```bash
make logs
make shell
make migrate
make fixtures
make worker-logs
make worker-restart
make down
```

## Проверка качества

```bash
make test
make phpstan
make cs-check
make cs-fix
make openapi-validate
make schema-validate
```

Назначение команд:

- `make test` — запуск backend-тестов;
- `make phpstan` — статический анализ PHP-кода на уровне 5;
- `make cs-check` — проверка форматирования PHP без изменения файлов;
- `make cs-fix` — автоматическое форматирование PHP;
- `make openapi-validate` — проверка OpenAPI-документа;
- `make schema-validate` — проверка соответствия Doctrine-моделей схеме БД.

Описание API в формате OpenAPI 3.0.3 находится в `docs/openapi.yaml`. Конфигурация Redocly находится в `docs/redocly.yaml`.

GitHub Actions выполняет:

- проверку Composer;
- PHP CS Fixer;
- PHPStan level 5;
- миграции Doctrine;
- проверку схемы БД;
- PHPUnit;
- unit-тесты Angular;
- production-сборку Angular;
- проверку OpenAPI;
- Playwright e2e.

CI использует PHP 8.3, Node.js 22 и PostgreSQL 16. Для e2e запускается полный Docker Compose stack с RabbitMQ и Messenger worker. Публикация образов и автоматический deployment не выполняются.

## Browser e2e

E2e-тесты Playwright запускаются на хосте с Node.js 22 и Chromium.

Браузер необходимо установить один раз:

```bash
make e2e-install
```

После этого следует запустить и подготовить приложение:

```bash
make up
make migrate
make fixtures
make e2e
```

Тесты используют реальные:

- Angular-приложение;
- backend API;
- PostgreSQL;
- RabbitMQ;
- Symfony Messenger worker.

Проверяются:

- вход с корректными и некорректными данными;
- восстановление сессии после перезагрузки;
- выход из системы;
- защита маршрутов;
- фильтрация товаров;
- серверная пагинация;
- переход в карточку товара;
- импорт корректного XLSX;
- отклонение файла с неправильным расширением.

Фикстура `frontend/e2e/fixtures/import-e2e.xlsx` не содержит ссылок на изображения, поэтому e2e-импорт не обращается к внешним серверам.

При ошибке HTML-отчёт создаётся в `frontend/playwright-report`, а трассировки и скриншоты — в `frontend/test-results`. Оба каталога исключены из Git.

## Авторизация и API

Для получения JWT необходимо выполнить:

```http
POST /api/auth/login
```

Токен передаётся в защищённые endpoints через заголовок:

```text
Authorization: Bearer <token>
```

Публичные endpoints:

- `GET /api/health`
- `POST /api/auth/login`
- `GET /api/product-images/{id}/content`

Endpoints товаров и импорта требуют авторизации.

API чтения товаров:

```http
GET /api/products?page=1&limit=20
GET /api/products?name=бермуды
GET /api/products?minPrice=500&maxPrice=1500
GET /api/products/{id}
```

Swagger UI не используется. Полная спецификация запросов, ответов и кодов ошибок находится в `docs/openapi.yaml`.

## Асинхронный импорт XLSX

Для запуска импорта отправьте файл в multipart-поле `file`:

```bash
curl \
  -H "Authorization: Bearer TOKEN" \
  -F "file=@backend/tests/Fixtures/import-example.xlsx" \
  http://localhost:8080/api/imports
```

Запрос возвращает HTTP 202 и идентификатор задачи импорта.

Состояние задачи можно получить через:

```http
GET /api/imports/{id}
```

Ответ содержит:

- статус задачи;
- общий размер импорта;
- количество обработанных строк;
- количество успешно импортированных строк;
- количество ошибочных строк;
- прогресс;
- первые 100 ошибок и предупреждений.

Первоначальный HTTP-запрос только проверяет и сохраняет загруженный файл, создаёт задачу и публикует `ImportProductsMessage`.

Обработку выполняет worker:

```bash
php bin/console messenger:consume async
```

В Docker Compose worker запускается отдельным сервисом и получает сообщения из RabbitMQ.

Управление worker:

```bash
make worker-logs
make worker-restart
```

Для Messenger настроена стратегия повторных попыток с экспоненциальной задержкой.

Ожидаемые ошибки отдельных строк преобразуются в `ImportError`. Критическая ошибка файла переводит задачу в статус `failed`. Такие ошибки не выбрасываются из handler и не запускают автоматическую повторную обработку всего сообщения.

Если сообщение повторно доставлено для прерванной задачи в статусе `processing`, старые счётчики и ошибки очищаются, после чего импорт запускается заново с первой строки. Upsert товаров делает повторную обработку идемпотентной.

Отдельный failure transport пока не настроен.

## Обработка файлов и изображений

Максимальный размер XLSX задаётся переменной:

```dotenv
IMPORT_MAX_FILE_SIZE=10485760
```

Ограничения PHP на размер POST-запроса и загружаемого файла также продолжают действовать.

Загруженные XLSX-файлы хранятся в:

```text
backend/storage/imports
```

Скачанные изображения хранятся в:

```text
backend/storage/images
```

Имя локального файла формируется из хеша URL. В базе данных сохраняется путь относительно `backend/storage`.

Каждая непустая колонка вида `Доп. поле: ...` преобразуется в атрибут товара. Ключом становится часть названия после `Доп. поле: `.

Исключения:

- `Ссылка на упаковку`;
- `Ссылки на фото`.

Значения этих колонок объединяются и обрабатываются как ссылки на изображения.

Если изображение недоступно, товар всё равно импортируется. Для изображения создаётся `ProductImage` с `path = null`, а в задачу импорта добавляется предупреждение.

Внешний сервер может вернуть, например, HTTP 403. Такой результат считается предупреждением, а не ошибкой строки.

## Ограничение запуска импорта

Количество запусков импорта ограничивается отдельно для каждого авторизованного пользователя. Состояние ограничения хранится в PostgreSQL и работает между разными PHP-FPM процессами.

Настройки по умолчанию:

```dotenv
IMPORT_RATE_LIMIT=5
IMPORT_RATE_WINDOW_SECONDS=60
```

Таким образом, один пользователь может запустить не более пяти импортов за 60 секунд.

При превышении ограничения API возвращает HTTP 429 с заголовком `Retry-After`.

Проверка выполняется до:

- сохранения файла;
- создания задачи импорта;
- публикации сообщения в RabbitMQ.

## JWT

Angular хранит JWT и данные пользователя в `localStorage`.

В проекте не реализованы:

- refresh token;
- регистрация;
- роли и ACL;
- восстановление пароля.

После истечения JWT пользователю необходимо войти повторно.

Параметры JWT задаются через:

```dotenv
JWT_SECRET=local-development-secret-change-before-production-123456
JWT_TTL=3600
```

Значение `JWT_SECRET` из `.env.example` предназначено только для локальной разработки. В production его необходимо заменить на случайный секретный ключ.