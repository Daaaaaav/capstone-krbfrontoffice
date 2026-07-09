# KRB System - Run Guide

This project supports multiple deployment methods:

- **Docker Production Deployment** (recommended for production)
- **Docker Development** (for local development)
- **Native Local Setup** (Fedora/Linux focused)

The system includes:
- Laravel 12 application with Livewire 3
- Python LSTM AI service for predictive analytics
- MySQL database
- Optional Wazuh integration for security monitoring

## 1. Clone Project

```bash
git clone <your-repo-url>
cd KRB-System
```

---

## 2. Docker Production Deployment (Recommended)

**For production deployment with auto-starting AI services.**

### Quick Deploy

**Windows:**
```bash
.\deploy.bat
```

**Linux/macOS:**
```bash
chmod +x deploy.sh
./deploy.sh
```

The deployment script will:
1. Build Docker images (Laravel + Python LSTM service)
2. Start all services (App, MySQL, LSTM, Nginx)
3. Run database migrations
4. Optimize the application

### Access Points

- **Application**: http://localhost
- **LSTM API**: http://localhost:8001
- **LSTM Docs**: http://localhost:8001/docs
- **MySQL**: localhost:3307

### Default Login

- **Manager**: `manager@krbogor.id` / `superpassword`
- **Receptionist**: `receptionist@krbogor.id` / `receppassword`

### Documentation

- **Quick Start**: [DOCKER_QUICKSTART.md](DOCKER_QUICKSTART.md) - 5-minute guide
- **Full Guide**: [DEPLOYMENT.md](DEPLOYMENT.md) - Complete deployment documentation
- **Setup Summary**: [DOCKER_SETUP_COMPLETE.md](DOCKER_SETUP_COMPLETE.md) - What was configured

### Common Commands

```bash
# View logs
docker-compose logs -f

# Stop services
docker-compose down

# Restart services
docker-compose restart

# Run artisan commands
docker-compose exec app php artisan [command]
```

---

## 3. Docker Development Setup

Use Docker if your team needs the same setup across Windows and Linux.

Note for Fedora/RHEL users: bind mounts use SELinux relabel (`:z`) in compose so containers can read project files.

### Linux/macOS (helper script)

```bash
chmod +x scripts/docker-dev.sh
./scripts/docker-dev.sh init
./scripts/docker-dev.sh start
```

### Windows PowerShell (helper script)

```powershell
.\scripts\docker-dev.ps1 -Action init
.\scripts\docker-dev.ps1 -Action start
```

Open:

- App: http://localhost:8000
- Vite: http://localhost:5173
- MySQL (host): localhost:3307

If `3307` is busy too, set a different value in `.env` before start:

```env
MYSQL_HOST_PORT=3310
```

### Start with Wazuh (optional)

Linux/macOS:

```bash
./scripts/docker-dev.sh start --wazuh
```

Windows PowerShell:

```powershell
.\scripts\docker-dev.ps1 -Action start -Wazuh
```

### Common Docker helper commands

Linux/macOS:

```bash
./scripts/docker-dev.sh logs
./scripts/docker-dev.sh logs --wazuh
./scripts/docker-dev.sh test
./scripts/docker-dev.sh stop
./scripts/docker-dev.sh stop --volumes
```

Windows PowerShell:

```powershell
.\scripts\docker-dev.ps1 -Action logs
.\scripts\docker-dev.ps1 -Action logs -Wazuh
.\scripts\docker-dev.ps1 -Action test
.\scripts\docker-dev.ps1 -Action stop
.\scripts\docker-dev.ps1 -Action stop -Volumes
```

Manual Docker commands are documented in docs/DOCKER_SETUP.md.

## 4. Local Development Setup

**For local development without Docker (Laragon, XAMPP, or native).**

📖 **See complete guide**: [LOCAL_DEVELOPMENT.md](LOCAL_DEVELOPMENT.md)

### Quick Start (Laragon/Windows)

1. Place project in `C:\laragon\www\KRB-System\`
2. Copy `.env.example` to `.env`
3. Create database: `krbs`
4. Install dependencies:
   ```bash
   composer install
   npm install
   ```
5. Setup application:
   ```bash
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   npm run build
   ```
6. Access: http://krb-system.test

### Quick Start (Native Linux/macOS)

1. Install prerequisites:
   ```bash
   # Ubuntu/Debian
   sudo apt install -y php8.3 php8.3-mysql composer nodejs npm mysql-server
   
   # macOS
   brew install php@8.3 composer node mysql
   ```

2. Clone and setup:
   ```bash
   git clone <repo-url>
   cd KRB-System
   cp .env.example .env
   ```

3. Create database:
   ```bash
   mysql -u root -p
   CREATE DATABASE krbs;
   EXIT;
   ```

4. Install and run:
   ```bash
   composer install
   npm install
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   npm run build
   php artisan serve
   ```

5. Access: http://localhost:8000

**For detailed instructions, troubleshooting, and platform-specific guides, see [LOCAL_DEVELOPMENT.md](LOCAL_DEVELOPMENT.md)**

## 5. AI Services (Optional)

### LSTM Prediction Service

The system includes a Python-based LSTM service for predictive analytics.

**Local Development:**

1. Install Python dependencies:
   ```bash
   pip install --user fastapi uvicorn tensorflow pandas scikit-learn holidays
   ```

2. Start service:
   ```bash
   # Windows
   .\start_lstm_service.bat
   
   # Linux/macOS
   python3 -m uvicorn app.Services.AI.LSTM_Service:app --host 127.0.0.1 --port 8001
   ```

3. Verify:
   ```bash
   curl http://127.0.0.1:8001/
   ```

**Docker Deployment:**

LSTM service automatically starts with the application (no manual setup needed).

**Features:**
- 3-week booking predictions
- Holiday-aware forecasting
- Weather integration (BMKG API)
- Occupancy forecasting
- Anomaly detection

**Note:** The application works without LSTM service using fallback predictions.

---

## 6. Wazuh (Optional)

If Wazuh is not available, the security report page can still run by reading Laravel logs.

Fedora install script:

```bash
chmod +x scripts/wazuh_install_manager_fedora.sh
./scripts/wazuh_install_manager_fedora.sh
```

Test simulation:

```bash
chmod +x scripts/wazuh_test_simulation.sh
./scripts/wazuh_test_simulation.sh
```

Check alerts:

```bash
sudo tail -f /var/ossec/logs/alerts/alerts.log
```

One-command web test:

```bash
chmod +x scripts/run_wazuh_web_test.sh scripts/wazuh_test_simulation.sh
./scripts/run_wazuh_web_test.sh
```

```

## 7. Integrations (Google Meet & Zoom)

The system supports automatic generation of online meeting links for Google Meet and Zoom when booking rooms.

### Google Meet Setup
The system uses Google OAuth 2.0 to support both free Gmail accounts and Google Workspace accounts.
1. Create an OAuth Client ID (Web Application) in Google Cloud Console.
2. Add your deployment URL (`https://your-domain.com/google/callback`) and local URL (`http://127.0.0.1:8000/google/callback`) to the Authorized Redirect URIs.
3. Download the credentials as `client_secret.json` and place it in `storage/app/google/`.
4. Add your email to the **Test users** list in Google Cloud (if the app is in Testing mode).
5. Login as a Manager in the application, go to **Settings > Integrations**, and click **Connect Google Meet**.

### Zoom Setup
The system uses Zoom Server-to-Server OAuth.
1. Create a Server-to-Server OAuth app in the [Zoom App Marketplace](https://marketplace.zoom.us/).
2. Copy your credentials into `.env`:
   ```env
   ZOOM_ACCOUNT_ID=your_account_id
   ZOOM_CLIENT_ID=your_client_id
   ZOOM_CLIENT_SECRET=your_client_secret
   ```
3. Ensure the app is activated in the marketplace.

## 8. Useful Commands

```bash
php artisan test
php artisan optimize:clear
tail -f storage/logs/laravel.log
```

## 9. Notes

- **Production**: Use Docker deployment (section 2) for production environments
- **Development**: Use Docker development (section 3) or native setup (section 4)
- **AI Services**: LSTM service auto-starts in Docker deployment, manual start for local dev
- **Security**: Keep secrets and real credentials out of git
- **Wazuh**: Optional security monitoring, update PROJECT_PATH in scripts if needed
- **Integrations**: Make sure to renew Zoom Server-to-Server tokens or Google OAuth test status if they expire.

## 10. Project Structure

```
KRB-System/
├── app/
│   ├── Livewire/          # Livewire components
│   ├── Models/            # Eloquent models
│   ├── Services/
│   │   └── AI/            # AI services (LSTM, DataPreprocessor, etc.)
│   └── Http/              # Controllers and middleware
├── docker/                # Docker configuration files
│   ├── nginx/             # Nginx configuration
│   ├── php/               # PHP-FPM configuration
│   └── supervisor/        # Process manager configuration
├── resources/
│   └── views/             # Blade templates
├── routes/                # Application routes
├── Dockerfile             # Multi-stage Docker build
├── docker-compose.yml     # Docker orchestration
├── deploy.bat             # Windows deployment script
├── deploy.sh              # Linux/macOS deployment script
└── DEPLOYMENT.md          # Full deployment documentation
```

## 11. Support

For issues or questions:

1. **Docker Deployment**: See [DEPLOYMENT.md](DEPLOYMENT.md)
2. **Quick Start**: See [DOCKER_QUICKSTART.md](DOCKER_QUICKSTART.md)
3. **Check Logs**: `docker-compose logs -f` or `tail -f storage/logs/laravel.log`
4. **Verify Services**: `docker-compose ps`

## 12. License

This project is part of the KRB System.
