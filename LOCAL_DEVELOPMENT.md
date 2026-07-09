# Local Development Guide

This guide covers running the KRB System locally without Docker (using Laragon, XAMPP, or native setup).

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Quick Start with Laragon (Windows)](#quick-start-with-laragon-windows)
3. [Quick Start with XAMPP](#quick-start-with-xampp)
4. [Native Setup (Linux/macOS)](#native-setup-linuxmacos)
5. [Running the LSTM Service](#running-the-lstm-service)
6. [Common Commands](#common-commands)
7. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Software

- **PHP**: 8.3 or higher
- **Composer**: 2.x
- **Node.js**: 18.x or higher
- **MySQL/MariaDB**: 8.0 or higher
- **Python**: 3.12 (for LSTM service)

### Check Versions

```bash
php -v
composer -v
node -v
npm -v
mysql --version
python --version
```

---

## Quick Start with Laragon (Windows)

### 1. Install Laragon

Download from: https://laragon.org/download/

Laragon includes PHP, MySQL, Node.js, and more.

### 2. Clone Project

Place the project in Laragon's `www` directory:

```
C:\laragon\www\KRB-System\
```

### 3. Configure Environment

Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

Edit `.env`:

```env
APP_NAME="KRB System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://krb-system.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=krbs
DB_USERNAME=root
DB_PASSWORD=

SYSTEM_MODE=development

# LSTM Service (local)
LSTM_SERVICE_URL=http://127.0.0.1:8001
```

### 4. Create Database

Open Laragon → Database → Create database `krbs`

Or via MySQL CLI:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE krbs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### 5. Install Dependencies

```bash
# PHP dependencies
composer install

# Node dependencies
npm install
```

### 6. Generate Application Key

```bash
php artisan key:generate
```

### 7. Run Migrations

```bash
php artisan migrate
```

### 8. Seed Database (Optional)

```bash
php artisan db:seed
```

### 9. Build Frontend Assets

```bash
npm run build
```

Or for development with hot reload:

```bash
npm run dev
```

### 10. Start Laravel

Laragon automatically serves the application at:

**http://krb-system.test**

Or manually start the server:

```bash
php artisan serve
```

Access at: **http://127.0.0.1:8000**

### 11. Start LSTM Service (Optional)

Open a new terminal:

```bash
.\start_lstm_service.bat
```

Or manually:

```bash
python -m uvicorn app.Services.AI.LSTM_Service:app --host 127.0.0.1 --port 8001
```

---

## Quick Start with XAMPP

### 1. Install XAMPP

Download from: https://www.apachefriends.org/

### 2. Install Composer

Download from: https://getcomposer.org/download/

### 3. Install Node.js

Download from: https://nodejs.org/

### 4. Clone Project

Place in XAMPP's `htdocs` directory:

```
C:\xampp\htdocs\KRB-System\
```

### 5. Start XAMPP

Start Apache and MySQL from XAMPP Control Panel.

### 6. Configure Environment

Copy `.env.example` to `.env`:

```bash
copy .env.example .env
```

Edit `.env`:

```env
APP_NAME="KRB System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/KRB-System/public

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=krbs
DB_USERNAME=root
DB_PASSWORD=

SYSTEM_MODE=development
LSTM_SERVICE_URL=http://127.0.0.1:8001
```

### 7. Create Database

Open phpMyAdmin: http://localhost/phpmyadmin

Create database: `krbs`

### 8. Install Dependencies

```bash
cd C:\xampp\htdocs\KRB-System
composer install
npm install
```

### 9. Setup Application

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
```

### 10. Access Application

**http://localhost/KRB-System/public**

### 11. Start LSTM Service (Optional)

```bash
.\start_lstm_service.bat
```

---

## Native Setup (Linux/macOS)

### 1. Install Prerequisites

**Ubuntu/Debian:**

```bash
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-xml \
    php8.3-mbstring php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath \
    mysql-server composer nodejs npm python3 python3-pip
```

**macOS (Homebrew):**

```bash
brew install php@8.3 composer node mysql python@3.12
brew services start mysql
```

**Fedora:**

```bash
sudo dnf install -y php php-cli php-mbstring php-xml php-pdo php-mysqlnd \
    php-bcmath php-curl php-zip unzip composer nodejs npm mariadb-server \
    python3 python3-pip
sudo systemctl enable --now mariadb
```

### 2. Clone Project

```bash
git clone <your-repo-url>
cd KRB-System
```

### 3. Configure Environment

```bash
cp .env.example .env
```

Edit `.env`:

```env
APP_NAME="KRB System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=krbs
DB_USERNAME=krbs_user
DB_PASSWORD=your_password

SYSTEM_MODE=development
LSTM_SERVICE_URL=http://127.0.0.1:8001
```

### 4. Create Database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE krbs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'krbs_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON krbs.* TO 'krbs_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Install Dependencies

```bash
composer install
npm install
```

### 6. Setup Application

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
```

### 7. Start Development Server

**Option A: Single Command (Recommended)**

```bash
php artisan serve
```

Access at: http://localhost:8000

**Option B: Separate Processes**

Terminal 1 - Laravel:
```bash
php artisan serve
```

Terminal 2 - Queue Worker:
```bash
php artisan queue:work
```

Terminal 3 - Vite (Hot Reload):
```bash
npm run dev
```

### 8. Start LSTM Service (Optional)

Terminal 4:
```bash
python3 -m uvicorn app.Services.AI.LSTM_Service:app --host 127.0.0.1 --port 8001
```

---

## Running the LSTM Service

The LSTM service is optional but required for AI predictions.

### Install Python Dependencies

**Windows:**

```bash
pip install --user fastapi==0.135.3 uvicorn[standard]==0.42.0 tensorflow==2.18.0 pandas==2.2.3 scikit-learn==1.6.1 holidays==0.62
```

**Linux/macOS:**

```bash
pip3 install --user fastapi==0.135.3 uvicorn[standard]==0.42.0 tensorflow==2.18.0 pandas==2.2.3 scikit-learn==1.6.1 holidays==0.62
```

Or use the batch script (already created):

```bash
.\install_python_packages.bat
```

### Start LSTM Service

**Windows:**

```bash
.\start_lstm_service.bat
```

**Linux/macOS:**

```bash
python3 -m uvicorn app.Services.AI.LSTM_Service:app --host 127.0.0.1 --port 8001
```

### Verify LSTM Service

```bash
curl http://127.0.0.1:8001/
```

Expected response:
```json
{
  "status": "healthy",
  "service": "Improved LSTM Forecast Service",
  "version": "2.0.0"
}
```

### Test LSTM Predictions

```bash
php artisan ai:test-3weeks
```

---

## Common Commands

### Laravel Commands

```bash
# Start development server
php artisan serve

# Clear all caches
php artisan optimize:clear

# Clear specific caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Seed database
php artisan db:seed

# Create new migration
php artisan make:migration create_table_name

# Create new model
php artisan make:model ModelName

# Create new Livewire component
php artisan make:livewire ComponentName

# Run queue worker
php artisan queue:work

# Run scheduler (for testing)
php artisan schedule:run

# Access tinker (REPL)
php artisan tinker

# View routes
php artisan route:list

# Test LSTM integration
php artisan ai:test-3weeks
```

### Frontend Commands

```bash
# Install dependencies
npm install

# Build for production
npm run build

# Development with hot reload
npm run dev

# Watch for changes
npm run watch
```

### Database Commands

```bash
# Access MySQL CLI
mysql -u root -p

# Export database
mysqldump -u root -p krbs > backup.sql

# Import database
mysql -u root -p krbs < backup.sql

# Show databases
mysql -u root -p -e "SHOW DATABASES;"
```

---

## Development Workflow

### Typical Development Session

1. **Start MySQL** (if not auto-started)
   - Laragon: Auto-starts
   - XAMPP: Start from control panel
   - Native: `sudo systemctl start mysql` or `brew services start mysql`

2. **Start Laravel**
   ```bash
   php artisan serve
   ```

3. **Start Vite (for hot reload)**
   ```bash
   npm run dev
   ```

4. **Start Queue Worker** (if using queues)
   ```bash
   php artisan queue:work
   ```

5. **Start LSTM Service** (optional)
   ```bash
   .\start_lstm_service.bat
   ```

### Making Changes

**Backend (PHP/Laravel):**
- Edit files in `app/`, `routes/`, `config/`
- Changes are reflected immediately (no restart needed)
- Clear cache if config changes: `php artisan config:clear`

**Frontend (Blade/CSS/JS):**
- Edit files in `resources/views/`, `resources/css/`, `resources/js/`
- With `npm run dev`: Changes hot-reload automatically
- With `npm run build`: Run build after changes

**Database:**
- Create migration: `php artisan make:migration`
- Run migration: `php artisan migrate`
- Rollback: `php artisan migrate:rollback`

---

## Troubleshooting

### Port Already in Use

**Error:** `Address already in use`

**Solution:** Use a different port:

```bash
php artisan serve --port=8080
```

### Database Connection Failed

**Error:** `SQLSTATE[HY000] [2002] Connection refused`

**Solutions:**

1. Check MySQL is running:
   ```bash
   # Windows (Laragon/XAMPP)
   Check control panel
   
   # Linux
   sudo systemctl status mysql
   
   # macOS
   brew services list
   ```

2. Verify credentials in `.env`:
   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=krbs
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. Test connection:
   ```bash
   mysql -u root -p -h 127.0.0.1
   ```

### LSTM Service Not Starting

**Error:** `ModuleNotFoundError: No module named 'fastapi'`

**Solution:** Install Python dependencies:

```bash
pip install --user fastapi uvicorn tensorflow pandas scikit-learn holidays
```

**Error:** `Address already in use (port 8001)`

**Solution:** Kill process using port 8001:

```bash
# Windows
netstat -ano | findstr :8001
taskkill /PID <PID> /F

# Linux/macOS
lsof -i :8001
kill -9 <PID>
```

### Permission Errors

**Error:** `Permission denied` on `storage/` or `bootstrap/cache/`

**Solution:**

```bash
# Windows (run as Administrator)
icacls storage /grant Everyone:F /T
icacls bootstrap\cache /grant Everyone:F /T

# Linux/macOS
chmod -R 775 storage bootstrap/cache
chown -R $USER:www-data storage bootstrap/cache
```

### Composer Install Fails

**Error:** `Your requirements could not be resolved`

**Solutions:**

1. Update Composer:
   ```bash
   composer self-update
   ```

2. Clear Composer cache:
   ```bash
   composer clear-cache
   ```

3. Install with verbose output:
   ```bash
   composer install -vvv
   ```

### NPM Install Fails

**Error:** `EACCES: permission denied`

**Solution:**

```bash
# Clear npm cache
npm cache clean --force

# Install with verbose output
npm install --verbose
```

### Vite Not Working

**Error:** `Failed to resolve import`

**Solutions:**

1. Clear Vite cache:
   ```bash
   rm -rf node_modules/.vite
   npm run dev
   ```

2. Rebuild node_modules:
   ```bash
   rm -rf node_modules package-lock.json
   npm install
   npm run dev
   ```

### Class Not Found

**Error:** `Class 'App\...' not found`

**Solution:** Regenerate autoload files:

```bash
composer dump-autoload
```

---

## Environment Variables Reference

### Development Settings

```env
# Application
APP_NAME="KRB System"
APP_ENV=local                    # local for development
APP_DEBUG=true                   # true for development
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=krbs
DB_USERNAME=root
DB_PASSWORD=

# System Mode
SYSTEM_MODE=development          # Disables OTP and CAPTCHA

# LSTM Service
LSTM_SERVICE_URL=http://127.0.0.1:8001

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug                  # debug for development

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Queue
QUEUE_CONNECTION=database        # or 'sync' for immediate execution

# Mail (for testing)
MAIL_MAILER=log                  # Logs emails instead of sending
```

---

## Default Login Credentials

After seeding the database:

**Manager:**
- Email: `manager@krbogor.id`
- Password: `superpassword`

**Receptionist:**
- Email: `receptionist@krbogor.id`
- Password: `receppassword`

---

## Access Points

| Service | URL | Description |
|---------|-----|-------------|
| **Application** | http://localhost:8000 | Main web interface |
| **LSTM API** | http://127.0.0.1:8001 | AI prediction service |
| **LSTM Docs** | http://127.0.0.1:8001/docs | Interactive API docs |
| **MySQL** | localhost:3306 | Database |

---

## Tips for Development

### 1. Use Tinker for Testing

```bash
php artisan tinker
```

```php
// Test database connection
DB::connection()->getPdo();

// Query users
User::all();

// Test LSTM client
$client = new App\Services\AI\LSTMClient();
$client->isAvailable();
```

### 2. Watch Logs

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Query logs (add to .env)
DB_LOG_QUERIES=true
```

### 3. Use Debug Bar (Optional)

```bash
composer require barryvdh/laravel-debugbar --dev
```

### 4. IDE Helpers (Optional)

```bash
composer require --dev barryvdh/laravel-ide-helper
php artisan ide-helper:generate
php artisan ide-helper:models
```

---

## Next Steps

1. **Start Development**: Follow the quick start for your platform
2. **Explore Code**: Check `app/Livewire/` for components
3. **Test Features**: Login and explore the dashboard
4. **Make Changes**: Edit files and see changes live
5. **Deploy**: When ready, use Docker deployment (see [DEPLOYMENT.md](DEPLOYMENT.md))

---

## Support

For issues:

1. Check logs: `storage/logs/laravel.log`
2. Clear caches: `php artisan optimize:clear`
3. Verify environment: `.env` settings
4. Check services: MySQL, LSTM service
5. Review this guide's troubleshooting section

---
