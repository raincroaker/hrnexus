# HRNexus VPS Deployment Checklist

## 📋 System Requirements

### PHP & Server
- **PHP**: 8.2.12 or higher (^8.2)
- **Composer**: Latest version
- **Node.js**: 18+ (for npm and Vite)
- **npm**: Latest version
- **Web Server**: Nginx or Apache
- **Process Manager**: Supervisor or PM2 (for queues and Reverb)

---

## 🗄️ Database

### Required
- **MySQL** or **MariaDB** (currently using MySQL)
- **Database Name**: Set in `DB_DATABASE` env variable
- **User & Password**: Set in `DB_USERNAME` and `DB_PASSWORD`

### Configuration
- Default connection: `mysql`
- Port: `3306` (default)
- Host: `127.0.0.1` or your database server

---

## 🔍 Search Engine: Meilisearch

### Option 1: Meilisearch Cloud (Recommended)
- Sign up at: https://www.meilisearch.com/cloud
- Get your **Host URL** and **API Key**
- Set environment variables:
  ```
  SCOUT_DRIVER=meilisearch
  MEILISEARCH_HOST=https://your-instance.meilisearch.io
  MEILISEARCH_KEY=your_master_key_here
  ```

### Option 2: Self-Hosted Meilisearch
- Install Meilisearch on your VPS:
  ```bash
  curl -L https://install.meilisearch.com | sh
  ./meilisearch --master-key="your_master_key_here"
  ```
- Or use Docker:
  ```bash
  docker run -d -p 7700:7700 -e MEILI_MASTER_KEY=your_master_key getmeili/meilisearch:latest
  ```
- Set environment variables:
  ```
  SCOUT_DRIVER=meilisearch
  MEILISEARCH_HOST=http://localhost:7700
  MEILISEARCH_KEY=your_master_key_here
  ```

### Important Notes
- Meilisearch uses **vector search** with **3072 dimensions** (text-embedding-3-large)
- Index name: `documents`
- After deployment, run: `php artisan scout:sync-index-settings`

---

## 🤖 OpenAI API

### Required
- **OpenAI API Key** (from https://platform.openai.com/api-keys)
- **Model Used**: `gpt-4o` (for text extraction)
- **Embedding Model**: `text-embedding-3-large` (3072 dimensions)

### Environment Variables
```
OPENAI_API_KEY=sk-your-api-key-here
OPENAI_ORGANIZATION=org-your-org-id (optional)
OPENAI_PROJECT=proj-your-project-id (optional)
```

### Usage
- Text extraction from PDF, Word, PowerPoint files
- Embedding generation for vector search
- **Cost**: Pay-per-use (check OpenAI pricing)

---

## 📡 Real-time: Laravel Reverb

### Required
- **Laravel Reverb** server running
- **Port**: 8080 (default, configurable)
- **WebSocket support** in your web server

### Installation
```bash
php artisan reverb:install
```

### Running Reverb
```bash
php artisan reverb:start
```

### For Production (with Supervisor)
Create `/etc/supervisor/conf.d/reverb.conf`:
```ini
[program:reverb]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan reverb:start
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/reverb.log
stopwaitsecs=3600
```

### Environment Variables
```
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=your-domain.com (or IP)
REVERB_PORT=8080
REVERB_SCHEME=https (or http)

# Frontend (Vite)
VITE_REVERB_APP_KEY=your-app-key
VITE_REVERB_HOST=your-domain.com
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=https
```

### Nginx Configuration (for WebSocket)
Add to your Nginx config:
```nginx
location /reverb {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

---

## 📄 Document Processing: LibreOffice

### Required for Word/PPT to PDF Conversion
- **LibreOffice** must be installed on the server
- Used for converting `.docx` and `.pptx` files to PDF before OpenAI extraction

### Installation (Ubuntu/Debian)
```bash
sudo apt-get update
sudo apt-get install libreoffice --no-install-recommends
```

### Installation (CentOS/RHEL)
```bash
sudo yum install libreoffice
```

### Verification
```bash
which soffice
# Should return: /usr/bin/soffice
```

### Note
- The service will fall back to direct XML parsing if LibreOffice is not found
- But PDF conversion gives better results

---

## 🔄 Queue System

### Current Configuration
- **Driver**: `database` (default)
- **Table**: `jobs` (created by migration)

### For Production (Recommended: Redis)
1. Install Redis:
   ```bash
   sudo apt-get install redis-server
   ```

2. Update `.env`:
   ```
   QUEUE_CONNECTION=redis
   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379
   ```

3. Run queue worker with Supervisor:
   Create `/etc/supervisor/conf.d/laravel-worker.conf`:
   ```ini
   [program:laravel-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /path/to/your/project/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/path/to/your/project/storage/logs/worker.log
   stopwaitsecs=3600
   ```

---

## 📦 PHP Dependencies (Composer)

### Production Dependencies
```bash
composer install --optimize-autoloader --no-dev
```

### Key Packages
- `laravel/framework`: ^12.0
- `inertiajs/inertia-laravel`: ^2.0
- `laravel/fortify`: ^1.30 (Authentication)
- `laravel/reverb`: ^1.0 (WebSockets)
- `laravel/scout`: ^10.22 (Search)
- `meilisearch/meilisearch-php`: ^1.16 (Meilisearch client)
- `openai-php/laravel`: ^0.18.0 (OpenAI integration)
- `laravel/sanctum`: ^4.0 (API authentication)
- `laravel/wayfinder`: ^0.1.9 (Route helpers)

---

## 📦 Node.js Dependencies (npm)

### Production Build
```bash
npm install
npm run build
```

### Key Packages
- `@inertiajs/vue3`: ^2.1.0
- `vue`: ^3.5.13
- `tailwindcss`: ^4.1.1
- `vite`: ^7.0.4
- `laravel-echo`: ^2.2.6 (for Reverb)
- `pusher-js`: ^8.4.0 (WebSocket client)
- `axios`: ^1.13.2
- `chart.js`: ^4.5.1 (for charts)

---

## 🔐 Environment Variables (.env)

### Required Variables
```env
# Application
APP_NAME=HRNexus
APP_ENV=production
APP_KEY=base64:... (generate with: php artisan key:generate)
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrnexus
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Meilisearch
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=https://your-meilisearch-instance.com
MEILISEARCH_KEY=your_master_key

# OpenAI
OPENAI_API_KEY=sk-your-api-key

# Reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=your-domain.com
REVERB_PORT=8080
REVERB_SCHEME=https

# Frontend Reverb (for Vite)
VITE_REVERB_APP_KEY=your-app-key
VITE_REVERB_HOST=your-domain.com
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=https

# Queue
QUEUE_CONNECTION=database (or redis)

# Session
SESSION_DRIVER=database (or redis)

# Cache
CACHE_STORE=file (or redis)

# Mail (if using)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

---

## 🚀 Deployment Steps

### 1. Server Setup
```bash
# Update system
sudo apt-get update && sudo apt-get upgrade -y

# Install PHP 8.2
sudo apt-get install php8.2-fpm php8.2-cli php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 18+
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Install MySQL
sudo apt-get install mysql-server -y

# Install Nginx
sudo apt-get install nginx -y

# Install LibreOffice
sudo apt-get install libreoffice --no-install-recommends -y

# Install Redis (optional, recommended)
sudo apt-get install redis-server -y

# Install Supervisor
sudo apt-get install supervisor -y
```

### 2. Clone & Setup Project
```bash
# Clone your repository
cd /var/www
git clone your-repo-url hrnexus
cd hrnexus

# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node dependencies
npm install

# Build assets
npm run build

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Set permissions
sudo chown -R www-data:www-data /var/www/hrnexus
sudo chmod -R 755 /var/www/hrnexus
sudo chmod -R 775 /var/www/hrnexus/storage
sudo chmod -R 775 /var/www/hrnexus/bootstrap/cache
```

### 3. Database Setup
```bash
# Create database
mysql -u root -p
CREATE DATABASE hrnexus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hrnexus_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON hrnexus.* TO 'hrnexus_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Run migrations
php artisan migrate --force

# (Optional) Run seeders
php artisan db:seed
```

### 4. Configure Nginx
Create `/etc/nginx/sites-available/hrnexus`:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/hrnexus/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Reverb WebSocket
    location /reverb {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/hrnexus /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 5. Setup SSL (Let's Encrypt)
```bash
sudo apt-get install certbot python3-certbot-nginx -y
sudo certbot --nginx -d your-domain.com
```

### 6. Configure Supervisor
Create `/etc/supervisor/conf.d/reverb.conf` (see Reverb section above)
Create `/etc/supervisor/conf.d/laravel-worker.conf` (see Queue section above)

Reload Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 7. Meilisearch Setup
- If using cloud: Just set environment variables
- If self-hosting: Install and run Meilisearch (see Meilisearch section)

### 8. Final Steps
```bash
# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Sync Meilisearch index settings
php artisan scout:sync-index-settings

# Create storage link
php artisan storage:link

# Set up cron (if needed)
sudo crontab -e
# Add: * * * * * cd /var/www/hrnexus && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔍 Verification Checklist

- [ ] PHP 8.2+ installed and working
- [ ] Composer installed
- [ ] Node.js 18+ and npm installed
- [ ] MySQL database created and accessible
- [ ] Meilisearch running and accessible
- [ ] OpenAI API key configured
- [ ] LibreOffice installed (`soffice` command works)
- [ ] Reverb server running
- [ ] Queue workers running
- [ ] Nginx configured and serving site
- [ ] SSL certificate installed (HTTPS)
- [ ] Storage permissions set correctly
- [ ] Environment variables configured
- [ ] Assets built (`npm run build`)
- [ ] Migrations run successfully
- [ ] Meilisearch index synced

---

## 📝 Important Notes

1. **File Storage**: Documents are stored in `storage/app/private/documents/`
   - Ensure this directory has proper permissions
   - Consider backing up this directory regularly

2. **Meilisearch Index**: After deployment, you may need to re-index existing documents:
   ```bash
   php artisan scout:import "App\Models\Document"
   ```

3. **OpenAI Costs**: Monitor your OpenAI API usage as text extraction and embeddings consume tokens

4. **Reverb**: Ensure port 8080 is open in your firewall if using a separate server

5. **Queue Jobs**: If using database queue, ensure `jobs` table exists (created by migration)

6. **PWA**: The service worker (`sw.js`) and manifest (`manifest.json`) are in `public/` directory

---

## 🆘 Troubleshooting

### Meilisearch Connection Issues
- Check `MEILISEARCH_HOST` and `MEILISEARCH_KEY`
- Verify Meilisearch is running: `curl http://localhost:7700/health`

### Reverb Not Connecting
- Check Reverb is running: `php artisan reverb:start`
- Verify WebSocket proxy in Nginx
- Check firewall allows port 8080

### LibreOffice Not Found
- Verify installation: `which soffice`
- Check PATH includes LibreOffice binaries

### Queue Not Processing
- Check Supervisor is running: `sudo supervisorctl status`
- Check queue connection in `.env`
- Verify `jobs` table exists

---

## 📚 Additional Resources

- Laravel 12 Docs: https://laravel.com/docs/12.x
- Meilisearch Docs: https://www.meilisearch.com/docs
- Reverb Docs: https://laravel.com/docs/reverb
- OpenAI API Docs: https://platform.openai.com/docs

