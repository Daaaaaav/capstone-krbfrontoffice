# Docker Quick Start Guide

Get the KRB System running with Docker in 5 minutes.

## Prerequisites

- Docker Desktop installed ([Download here](https://www.docker.com/products/docker-desktop))
- 4GB RAM available
- 10GB disk space

## Quick Deploy

### Windows

1. Open PowerShell or Command Prompt
2. Navigate to project directory
3. Run:
   ```bash
   .\deploy.bat
   ```

### Linux/macOS

1. Open Terminal
2. Navigate to project directory
3. Run:
   ```bash
   chmod +x deploy.sh
   ./deploy.sh
   ```

## What Happens?

The deployment script will:

1. ✅ Check Docker installation
2. ✅ Create `.env` configuration file
3. ✅ Build Docker images (Laravel + Python LSTM)
4. ✅ Start all services (App, Database, AI)
5. ✅ Run database migrations
6. ✅ Optimize the application

## Access Your Application

After deployment completes (3-5 minutes):

| Service | URL | Description |
|---------|-----|-------------|
| **Web App** | http://localhost | Main application |
| **LSTM API** | http://localhost:8001 | AI prediction service |
| **API Docs** | http://localhost:8001/docs | Interactive API documentation |
| **MySQL** | localhost:3307 | Database (external access) |

## Default Login

**Manager:**
- Email: `manager@krbogor.id`
- Password: `superpassword`

**Receptionist:**
- Email: `receptionist@krbogor.id`
- Password: `receppassword`

## Verify Everything Works

### 1. Check Services

```bash
docker-compose ps
```

All services should show "Up" status.

### 2. Test LSTM Service

```bash
curl http://localhost:8001/
```

Should return:
```json
{
  "status": "healthy",
  "service": "Improved LSTM Forecast Service",
  "version": "2.0.0"
}
```

### 3. Test Application

Open browser: http://localhost

You should see the login page.

## Common Commands

### View Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f app
docker-compose logs -f lstm-service
```

### Stop Services

```bash
docker-compose down
```

### Restart Services

```bash
docker-compose restart
```

### Run Laravel Commands

```bash
docker-compose exec app php artisan [command]
```

Examples:
```bash
# Clear cache
docker-compose exec app php artisan cache:clear

# Run migrations
docker-compose exec app php artisan migrate

# Access tinker
docker-compose exec app php artisan tinker
```

## Troubleshooting

### Port Already in Use

If port 80 is already in use, edit `.env`:

```env
APP_PORT=8080
```

Then restart:
```bash
docker-compose down
docker-compose up -d
```

Access at: http://localhost:8080

### LSTM Service Not Starting

Check if you have enough RAM:
```bash
docker stats
```

LSTM service needs ~2GB RAM for TensorFlow.

### Database Connection Failed

Wait 30 seconds for MySQL to fully start, then:

```bash
docker-compose restart app
```

### Permission Errors

Fix storage permissions:
```bash
docker-compose exec app chown -R www-data:www-data /var/www/storage
docker-compose exec app chmod -R 755 /var/www/storage
```

## What's Running?

After deployment, you have:

- **Nginx**: Web server (port 80)
- **PHP-FPM**: Laravel application processor
- **MySQL 8.0**: Database (port 3307)
- **Python LSTM**: AI prediction service (port 8001)
- **Supervisor**: Process manager (queue workers, scheduler)

## Next Steps

1. **Configure for Production**: See [DEPLOYMENT.md](DEPLOYMENT.md)
2. **Set up SSL**: Use Traefik or Caddy reverse proxy
3. **Configure Backups**: Set up automated database backups
4. **Monitor Services**: Add Prometheus/Grafana monitoring

## Need Help?

- **Full Documentation**: [DEPLOYMENT.md](DEPLOYMENT.md)
- **Docker Details**: [docker/README.md](docker/README.md)
- **View Logs**: `docker-compose logs -f`
- **Check Status**: `docker-compose ps`

## Clean Up

To completely remove everything:

```bash
# Stop and remove containers
docker-compose down

# Remove volumes (⚠️ deletes database)
docker-compose down -v

# Remove images
docker-compose down --rmi all
```

---

**That's it!** Your KRB System is now running with Docker. 🚀
