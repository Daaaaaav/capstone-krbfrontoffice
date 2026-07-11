# Local Development - Quick Start

Get the KRB System running locally in 5 minutes.

## Choose Your Platform

- [Laragon (Windows)](#laragon-windows)
- [XAMPP (Windows/Mac/Linux)](#xampp-windowsmaclinux)
- [Native (Linux/macOS)](#native-linuxmacos)

---

## Laragon (Windows)

### 1. Setup

```bash
# Place project in
C:\laragon\www\KRB-System\

# Copy environment
cp .env.example .env

# Create database (via Laragon menu or MySQL CLI)
mysql -u root -p
CREATE DATABASE krbs;
EXIT;
```

### 2. Install & Run

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
```

### 3. Access

**http://krb-system.test** (auto-configured by Laragon)

Or manually: `php artisan serve` → http://localhost:8000

---

## XAMPP (Windows/Mac/Linux)

### 1. Setup

```bash
# Place project in
C:\xampp\htdocs\KRB-System\

# Copy environment
copy .env.example .env

# Edit .env
APP_URL=http://localhost/KRB-System/public

# Create database via phpMyAdmin
# http://localhost/phpmyadmin
# Create database: krbs
```

### 2. Install & Run

```bash
cd C:\xampp\htdocs\KRB-System
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
```

### 3. Access

**http://localhost/KRB-System/public**

---

## Native (Linux/macOS)

### 1. Install Prerequisites

**Ubuntu/Debian:**
```bash
sudo apt install -y php8.3 php8.3-mysql composer nodejs npm mysql-server
```

**macOS:**
```bash
brew install php@8.3 composer node mysql
brew services start mysql
```

### 2. Setup

```bash
# Clone project
git clone <repo-url>
cd KRB-System

# Copy environment
cp .env.example .env

# Create database
mysql -u root -p
CREATE DATABASE krbs;
CREATE USER 'krbs_user'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON krbs.* TO 'krbs_user'@'localhost';
EXIT;

# Edit .env with database credentials
```

### 3. Install & Run

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

### 4. Access

**http://localhost:8000**

---

## Start LSTM Service (Optional)

### Install Python Dependencies

```bash
# Windows
pip install --user fastapi uvicorn tensorflow pandas scikit-learn holidays

# Linux/macOS
pip3 install --user fastapi uvicorn tensorflow pandas scikit-learn holidays
```

### Start Service

**Windows:**
```bash
.\start_lstm_service.bat
```

**Linux/macOS:**
```bash
python3 -m uvicorn app.Services.AI.LSTM_Service:app --host 127.0.0.1 --port 8001
```

### Verify

```bash
curl http://127.0.0.1:8001/
```

Should return:
```json
{"status": "healthy", "service": "Improved LSTM Forecast Service"}
```

---

## Default Login

**Manager:**
- Email: `manager@krbogor.id`
- Password: `superpassword`

**Receptionist:**
- Email: `receptionist@krbogor.id`
- Password: `receppassword`

---

## Common Commands

```bash
# Start Laravel
php artisan serve

# Start Vite (hot reload)
npm run dev

# Clear cache
php artisan optimize:clear

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Run queue worker
php artisan queue:work

# Test LSTM
php artisan ai:test-3weeks

# View logs
tail -f storage/logs/laravel.log
```

---

## Troubleshooting

### Port 8000 Already in Use

```bash
php artisan serve --port=8080
```

### Database Connection Failed

Check `.env`:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=krbs
DB_USERNAME=root
DB_PASSWORD=
```

Test connection:
```bash
mysql -u root -p -h 127.0.0.1
```

### Permission Errors

**Windows:**
```bash
icacls storage /grant Everyone:F /T
icacls bootstrap\cache /grant Everyone:F /T
```

**Linux/macOS:**
```bash
chmod -R 775 storage bootstrap/cache
```

### Class Not Found

```bash
composer dump-autoload
```

### LSTM Service Not Starting

Check Python packages:
```bash
pip list | grep fastapi
pip list | grep tensorflow
```

Reinstall if missing:
```bash
pip install --user fastapi uvicorn tensorflow pandas scikit-learn holidays
```

---

## Development Workflow

### Typical Session

1. **Start MySQL** (if not auto-started)
2. **Start Laravel**: `php artisan serve`
3. **Start Vite**: `npm run dev` (optional, for hot reload)
4. **Start LSTM**: `.\start_lstm_service.bat` (optional)

### Making Changes

- **Backend**: Edit `app/`, changes reflect immediately
- **Frontend**: Edit `resources/views/`, auto-reloads with `npm run dev`
- **Database**: Create migration, run `php artisan migrate`

---

## Need More Help?
**Full Guide**: [LOCAL_DEVELOPMENT.md](LOCAL_DEVELOPMENT.md)
**Docker Deployment**: [DEPLOYMENT.md](DEPLOYMENT.md)
**Docker Quick Start**: [DOCKER_QUICKSTART.md](DOCKER_QUICKSTART.md)

---