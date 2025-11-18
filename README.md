# Pimono API

Laravel 12.0 API application with MySQL, Redis, and Laravel Horizon for queue management.

## Requirements

- Docker
- Docker Compose
- Composer authentication token (for private packages)

## Setup

### 1. Configure Composer Authentication

Create `auth.json` in the project root:

```json
{
  "http-basic": {
    "repo.packagist.com": {
      "username": "your-username",
      "password": "your-token"
    }
  }
}
```

### 2. Configure Environment

Update `.env` file with your settings. Key configurations:

```env
DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=pimono
DB_USERNAME=root
DB_PASSWORD=secret

REDIS_HOST=host.docker.internal
REDIS_PORT=6379
```

### 3. Start Services

```bash
docker-compose up -d
```

This starts:
- **pimono**: Laravel application (port 8200)
- **mysql**: Database server (port 3307)
- **redis**: Cache and queue backend (port 6380)

### 4. Access the Application

```
http://localhost:8200
```

## Services

| Service | Container | Host Port | Internal Port |
|---------|-----------|-----------|---------------|
| API     | pimono    | 8200      | 80            |
| MySQL   | pimono-mysql | 3307   | 3306          |
| Redis   | pimono-redis | 6380   | 6379          |

## Queue Workers

Queue workers run automatically via Supervisor inside the `pimono` container. No separate queue service needed.

## Database Migrations

Migrations run automatically on container startup via `start.sh`.

## Useful Commands

### View Logs

```bash
docker-compose logs -f pimono
docker-compose logs -f mysql
docker-compose logs -f redis
```

### Access Application Container

```bash
docker exec -it pimono bash
```

### Run Artisan Commands

```bash
docker exec -it pimono php artisan [command]
```

### Stop Services

```bash
docker-compose down
```

### Stop and Remove Volumes

```bash
docker-compose down -v
```

## Data Persistence

MySQL and Redis data persist in Docker volumes:
- `mysql_data`: Database files
- `redis_data`: Redis snapshots

## Troubleshooting

### Database Connection Issues

If you see connection errors, ensure MySQL is healthy:

```bash
docker-compose ps
```

Wait for `healthy` status before accessing the application.

### Port Conflicts

If ports 8200, 3307, or 6380 are in use, update `docker-compose.yml` port mappings.

### Reset Database

```bash
docker-compose down -v
docker-compose up -d
```
