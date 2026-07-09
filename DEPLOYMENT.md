# KRB System - Deployment Guide

This guide covers deploying the KRB System with Docker, including the Laravel application and Python LSTM AI service.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Quick Start](#quick-start)
3. [Manual Deployment](#manual-deployment)
4. [Configuration](#configuration)
5. [Verification](#verification)
6. [Troubleshooting](#troubleshooting)
7. [Production Checklist](#production-checklist)

---

## Prerequisites

### System Requirements

- **Docker Engine**: 20.10 or higher
- **Docker Compose**: 2.0 or higher
- **RAM**: Minimum 4GB available (8GB recommended)
- **Disk Space**: 10GB free space
- **OS**: Linux, macOS, or Windows with WSL2

### Install Docker

**Windows:**
- Download and install [Docker Desktop for Windows](https://docs.docker.com/desktop/install/windows-install/)
- Ensure WSL2 is enabled

**macOS:**
- Download and install [Docker Desktop for Mac](https://docs.docker.com/desktop/install/mac-install/)

**Linux:**
```bash
# Ubuntu/Debian
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER
```

---

## Quick Start

### Automated Deployment

**Windows:**
```bash
.\deploy.bat
```

**Linux/macOS:**
```bash
chmod +x deploy.sh
./deploy.sh
```

The script will:
1. Check Docker installation
2. Create `.env` file if missing
3. Build Docker images
4. Start all services
5. Run database migrations
6. Optimize the application

### Access the Application

After deployment completes:

- **Web Application**: http://localhost
- **LSTM API**: http://localhost:8001
- **LSTM API Docs**: http://localhost:8001/docs
- **MySQL**: localhost:3307

**Default Login:**
- Superadmin: `manager@krbogor.id` / `superpassword`
- Receptionist: `receptionist@krbogor.id` / `receppassword`

---

## Manual Deployment

### Step 1: Environment Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` and configure:

```env
# Application
APP_NAME="KRB System"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_URL=http://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=krbs
DB_USERNAME=krbs_user
DB_PASSWORD=your_secure_password_here

# System Mode (enables OTP and CAPTCHA in production)
SYSTEM_MODE=deployment

# LSTM Service (Docker internal network)
LSTM_SERVICE_URL=http://lstm-service:8001

# Docker Ports
MYSQL_HOST_PORT=3307
APP_PORT=80
```

### Step 2: Generate Application Key

```bash
docker run --rm -v $(pwd):/app -w /app php:8.3-cli php artisan key:generate --show
```

Copy the output and set it as `APP_KEY` in `.env`.

### Step 3: Build Docker Images

```bash
docker-compose build
```

This builds three images:
- Laravel application (PHP 8.3 + Nginx)
- Python LSTM service (TensorFlow + FastAPI)
- MySQL 8.0 database

### Step 4: Start Services

```bash
docker-compose up -d
```

### Step 5: Wait for Services

Check service health:

```bash
docker-compose ps
```

All services should show "healthy" status.

### Step 6: Run Migrations

```bash
docker-compose exec app php artisan migrate --force
```

### Step 7: Seed Database (Optional)

```bash
docker-compose exec app php artisan db:seed --force
```

### Step 8: Optimize Application

```bash
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
```

---

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `production` | Application environment |
| `APP_DEBUG` | `false` | Enable debug mode (disable in production) |
| `APP_KEY` | - | Application encryption key (required) |
| `APP_URL` | `http://localhost` | Application URL |
| `DB_HOST` | `mysql` | Database host (Docker service name) |
| `DB_DATABASE` | `krbs` | Database name |
| `DB_USERNAME` | `krbs_user` | Database username |
| `DB_PASSWORD` | `secret` | Database password |
| `SYSTEM_MODE` | `deployment` | `development` or `deployment` |
| `LSTM_SERVICE_URL` | `http://lstm-service:8001` | LSTM service URL |
| `MYSQL_HOST_PORT` | `3307` | MySQL external port |
| `APP_PORT` | `80` | Application external port |

### Service Ports

| Service | Internal Port | External Port | Description |
|---------|---------------|---------------|-------------|
| Nginx | 80 | 80 | Web server |
| PHP-FPM | 9000 | - | PHP processor |
| LSTM Service | 8001 | 8001 | AI predictions |
| MySQL | 3306 | 3307 | Database |

### Docker Volumes

| Volume | Purpose |
|--------|---------|
| `mysql_data` | Persistent database storage |
| `./storage` | Laravel storage (logs, cache, uploads) |
| `./bootstrap/cache` | Laravel bootstrap cache |

---

## Verification

### Check Service Status

```bash
docker-compose ps
```

Expected output:
```
NAME         IMAGE              STATUS         PORTS
krb-app      krb-app:latest     Up (healthy)   9000/tcp
krb-lstm     krb-lstm:latest    Up (healthy)   0.0.0.0:8001->8001/tcp
krb-mysql    mysql:8.0          Up (healthy)   0.0.0.0:3307->3306/tcp
krb-nginx    nginx:alpine       Up             0.0.0.0:80->80/tcp
```

### Test LSTM Service

```bash
curl http://localhost:8001/
```

Expected response:
```json
{
  "status": "healthy",
  "service": "Improved LSTM Forecast Service",
  "version": "2.0.0"
}
```

### Test Application

```bash
curl http://localhost/
```

Should return the login page HTML.

### View Logs

All services:
```bash
docker-compose logs -f
```

Specific service:
```bash
docker-compose logs -f app
docker-compose logs -f lstm-service
docker-compose logs -f mysql
```

---

## Troubleshooting

### LSTM Service Not Starting

**Symptom**: LSTM service shows "unhealthy" or exits immediately

**Solutions**:

1. Check memory availability (TensorFlow needs ~2GB):
   ```bash
   docker stats
   ```

2. View LSTM logs:
   ```bash
   docker-compose logs lstm-service
   ```

3. Verify Python dependencies:
   ```bash
   docker-compose exec lstm-service pip list
   ```

### Database Connection Failed

**Symptom**: Application shows "Connection refused" or "Access denied"

**Solutions**:

1. Check MySQL is healthy:
   ```bash
   docker-compose ps mysql
   ```

2. Verify database credentials in `.env`:
   ```bash
   docker-compose exec app php artisan tinker
   >>> DB::connection()->getPdo();
   ```

3. Check MySQL logs:
   ```bash
   docker-compose logs mysql
   ```

### Permission Errors

**Symptom**: "Permission denied" errors in logs

**Solutions**:

```bash
docker-compose exec app chown -R www-data:www-data /var/www/storage
docker-compose exec app chmod -R 755 /var/www/storage
docker-compose exec app chown -R www-data:www-data /var/www/bootstrap/cache
docker-compose exec app chmod -R 755 /var/www/bootstrap/cache
```

### Application Not Loading

**Symptom**: Blank page or 502 Bad Gateway

**Solutions**:

1. Check Nginx logs:
   ```bash
   docker-compose logs nginx
   ```

2. Check PHP-FPM logs:
   ```bash
   docker-compose exec app tail -f /var/log/supervisor/php-fpm.log
   ```

3. Verify PHP-FPM is running:
   ```bash
   docker-compose exec app ps aux | grep php-fpm
   ```

### Port Already in Use

**Symptom**: "Bind for 0.0.0.0:80 failed: port is already allocated"

**Solutions**:

1. Change port in `.env`:
   ```env
   APP_PORT=8080
   ```

2. Or stop the conflicting service:
   ```bash
   # Windows
   netstat -ano | findstr :80
   taskkill /PID <PID> /F
   
   # Linux/macOS
   sudo lsof -i :80
   sudo kill -9 <PID>
   ```

---

## Production Checklist

### Security

- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_ENV=production`
- [ ] Use strong `DB_PASSWORD` (16+ characters)
- [ ] Set `SYSTEM_MODE=deployment` (enables OTP and CAPTCHA)
- [ ] Configure SSL/TLS certificates
- [ ] Set up firewall rules
- [ ] Disable unnecessary ports
- [ ] Use secrets management for sensitive data

### Performance

- [ ] Enable opcache (already configured in `docker/php/php.ini`)
- [ ] Configure Redis for caching (optional)
- [ ] Set up CDN for static assets (optional)
- [ ] Configure log rotation
- [ ] Monitor resource usage

### Reliability

- [ ] Set up automated backups for MySQL
- [ ] Configure health checks
- [ ] Set up monitoring (Prometheus, Grafana, etc.)
- [ ] Configure log aggregation (ELK, Loki, etc.)
- [ ] Test disaster recovery procedures

### Backup Strategy

**Database Backup:**
```bash
# Create backup
docker-compose exec mysql mysqldump -u root -p krbs > backup_$(date +%Y%m%d).sql

# Restore backup
docker-compose exec -T mysql mysql -u root -p krbs < backup_20260514.sql
```

**Storage Backup:**
```bash
tar -czf storage_backup_$(date +%Y%m%d).tar.gz storage/
```

**Automated Backup Script:**
```bash
#!/bin/bash
BACKUP_DIR="/backups"
DATE=$(date +%Y%m%d_%H%M%S)

# Database
docker-compose exec -T mysql mysqldump -u root -p${DB_PASSWORD} krbs > ${BACKUP_DIR}/db_${DATE}.sql

# Storage
tar -czf ${BACKUP_DIR}/storage_${DATE}.tar.gz storage/

# Keep only last 7 days
find ${BACKUP_DIR} -name "*.sql" -mtime +7 -delete
find ${BACKUP_DIR} -name "*.tar.gz" -mtime +7 -delete
```

### SSL/TLS Configuration

For production, use a reverse proxy like Traefik or Caddy:

**Example with Traefik:**

Add to `docker-compose.yml`:

```yaml
services:
  traefik:
    image: traefik:v2.10
    command:
      - "--providers.docker=true"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.email=admin@example.com"
      - "--certificatesresolvers.letsencrypt.acme.storage=/letsencrypt/acme.json"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web"
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./letsencrypt:/letsencrypt
    networks:
      - krb-network

  nginx:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.krb.rule=Host(`your-domain.com`)"
      - "traefik.http.routers.krb.entrypoints=websecure"
      - "traefik.http.routers.krb.tls.certresolver=letsencrypt"
```

---

## Common Commands

### Service Management

```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# Restart services
docker-compose restart

# Restart specific service
docker-compose restart app

# View service status
docker-compose ps

# View resource usage
docker stats
```

### Application Management

```bash
# Run artisan commands
docker-compose exec app php artisan [command]

# Clear cache
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear

# Run migrations
docker-compose exec app php artisan migrate

# Access tinker
docker-compose exec app php artisan tinker

# View Laravel logs
docker-compose exec app tail -f storage/logs/laravel.log
```

### Database Management

```bash
# Access MySQL CLI
docker-compose exec mysql mysql -u root -p

# Run SQL file
docker-compose exec -T mysql mysql -u root -p krbs < script.sql

# Export database
docker-compose exec mysql mysqldump -u root -p krbs > backup.sql

# Import database
docker-compose exec -T mysql mysql -u root -p krbs < backup.sql
```

### Logs

```bash
# View all logs
docker-compose logs -f

# View specific service logs
docker-compose logs -f app
docker-compose logs -f lstm-service
docker-compose logs -f mysql
docker-compose logs -f nginx

# View last 100 lines
docker-compose logs --tail=100 app

# View logs since timestamp
docker-compose logs --since 2026-05-14T10:00:00 app
```

---

## Updating the Application

### Pull Latest Code

```bash
git pull origin main
```

### Rebuild and Restart

```bash
# Rebuild images
docker-compose build app lstm-service

# Restart services
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate --force

# Clear and rebuild cache
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
```

---

## Support

For detailed Docker configuration, see: [docker/README.md](docker/README.md)

For issues:
1. Check logs: `docker-compose logs -f`
2. Verify service health: `docker-compose ps`
3. Review Laravel logs: `storage/logs/laravel.log`
4. Check LSTM service: `curl http://localhost:8001/`

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                   Docker Network (krb-network)           │
│                                                          │
│  ┌──────────┐      ┌──────────┐      ┌──────────┐     │
│  │  Nginx   │─────▶│ Laravel  │─────▶│  MySQL   │     │
│  │  :80     │      │ PHP-FPM  │      │  :3306   │     │
│  └──────────┘      │  :9000   │      └──────────┘     │
│                    │          │                         │
│                    │ Supervisor:                        │
│                    │ - PHP-FPM                          │
│                    │ - Queue Workers                    │
│                    │ - Scheduler                        │
│                    └──────────┘                         │
│                         │                               │
│                         ▼                               │
│                    ┌──────────┐                         │
│                    │  LSTM    │                         │
│                    │ Service  │                         │
│                    │  :8001   │                         │
│                    │          │                         │
│                    │ FastAPI + TensorFlow               │
│                    └──────────┘                         │
└─────────────────────────────────────────────────────────┘
```

---

## License

This deployment configuration is part of the KRB System project.
