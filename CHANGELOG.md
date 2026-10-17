# Changelog

All notable changes to `mohannadelemary/laravel-redis-connection-clients` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-10-17

### Added
- Optional `client` key on any connection or cluster in `config/database.php` (`phpredis`, `predis`, or a client registered with `Redis::extend()`).
- Connections without the key keep using `database.redis.client`, resolved by Laravel's own manager.
- Clear exceptions for unknown or invalid client values.
