# Seed Anime Content — План реализации

## Контекст

В [`routes/console.php`](../../routes/console.php:23) уже зарегистрирована scheduled command `SeedAnimeContent::class` (everyMinute), но сам класс отсутствует. Нужно создать его по образу [`SeedWowContent`](../../app/Infrastructure/Console/Commands/SeedWowContent.php).

## Что нужно сделать

### 1. Создать `app/Infrastructure/Console/Commands/SeedAnimeContent.php`

Копия [`SeedWowContent.php`](../../app/Infrastructure/Console/Commands/SeedWowContent.php) со следующими отличиями:

| Параметр | SeedWowContent | SeedAnimeContent |
|----------|---------------|------------------|
| `$signature` | `app:seed-wow` | `app:seed-anime` |
| `$description` | WoW-каналы | Аниме-каналы |
| `CHANNELS` | 6 WoW-каналов | 6 аниме-каналов |

### 2. Список аниме YouTube-каналов

```php
private const CHANNELS = [
    'https://www.youtube.com/@Gigguk',
    'https://www.youtube.com/@TheAnimeMan',
    'https://www.youtube.com/@MothersBasement',
    'https://www.youtube.com/@GlassReflection',
    'https://www.youtube.com/@ChibiReviews',
    'https://www.youtube.com/@SuperEyepatchWolf',
];
```

### 3. Верификация

- `composer check` должен проходить зелёным
- Команда `php artisan app:seed-anime` должна быть доступна

## Файлы к изменению

| Файл | Действие |
|------|----------|
| `app/Infrastructure/Console/Commands/SeedAnimeContent.php` | Создать |
| `routes/console.php` | Уже содержит регистрацию (строка 23) — **не менять** |
