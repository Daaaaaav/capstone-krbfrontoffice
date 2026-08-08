# Code Setup
In a code editor of choice, clone the project repository and navigate to the project directory as follows
git clone https://github.com/Daaaaaav/caps-test 
cd <directory-name>

Copy the environment configuration file and make the as follows:
2.1. cp .env.example .env
2.2. php artisan storage:link 

And then, configure the database credentials and application settings inside the  
                	newly copied.env file.
                 
Install Application Dependencies
	   	Backend dependencies can be installed and configured via this command:
              	 4.1. composer install
		 4.2. composer dump-autoload 

Whilst frontend dependencies can be done as with this command:
               	5.1. npm install
		5.2  npm install chartjs

Generate the Laravel application key:
              	6.1. php artisan key:generate

# Database
7.1. Create a MySQL database for the application (e.g. capstonekrbs), then 
configure the database credentials in the .env file.

Afterwards, execute database migrations with the command:
8.1. php artisan migrate

Populate initial data with the seeder through inputting the command:
9.1. php artisan migrate --seed

Frontend Build Process
		Compile frontend assets via commanding:
10.1. npm run build
	
For active development, run the command and keep it running throughout opening the
app via:
11.1. npm run dev

Run Application Locally
		Start the Laravel development server via the command:
12.1. php artisan serve

This makes the application accessible through:
13.1. http://localhost:8000

Run the LSTM Forecasting Service
		The AI prediction module uses a Python FastAPI service with TensorFlow.

Install Python dependencies through this command:
pip install fastapi uvicorn tensorflow pandas scikit-learn holidays

Run the forecasting service this command:
16.1. python -m uvicorn app.Services.AI.LSTM_Service:app --host 127.0.0.1 --port 8001

Verify service availability through this command:
17.1. curl http://127.0.0.1:8001/

Expected output:
{
  "status": "healthy",
  "service": "Improved LSTM Forecast Service"
}

Nginx + PHP FPM Deployment
		For production deployment, Nginx and PHP 8.2 FPM  
                        is used to ensure environment consistency.

In the Ubuntu server, run:
19.1. ssh <username>@<ip address>
For our current development server, it is ssh dav@100.74.102.31

Clone the project repository in the web directory:
            	20.1. cd /var/www
                        20.2. git clone https://github.com/Daaaaaav/caps-test.git 
                        20.3. cd caps-test

Configure the server environment:
cp .env.example .env
nano .env 

Install PHP dependencies:
composer install --no-dev --optimize-autoloader

Install Frontend dependencies:
npm install
npm install chartjs
npm run build

Generate the Laravel Application Key:
php artisan key:generate 

Configure the database and run migrations for initial data:
CREATE DATABASE krbs; 
php artisan migrate 
php artisan db:seed 

Optimize Laravel:
php artisan config:cache 
php artisan route:cache
php artisan view:cache
php artisan optimize
 
Configure File Permissions:
sudo chown -R www-data:www-data .
sudo chmod -R 775 storage bootstrap/cache

 
Configure and Test Nginx:
Copy the supplied Nginx configuration into /etc/nginx/sites-available/receptionistkebunraya 
sudo ln -s /etc/nginx/sites-available/receptionistkebunraya \ /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx

Start and Check PHP-FPM: 
sudo systemctl restart php8.2-fpm 
sudo systemctl status php8.2-fpm

Start and Verify the LSTM Forecasting Service 
pip install fastapi uvicorn tensorflow pandas scikit-learn holidays 
python -m uvicorn app.Services.AI.LSTM_Service:app \ --host 127.0.0.1 \ --port 8001 
curl http://127.0.0.1:8001/ 
			if successful: 
{ "status":"healthy", "service":"Improved LSTM Forecast Service" } 

Updating an Existing Deployment
Navigate to the project directory
1.1. cd /var/www/caps-test

Retrieve the last source code
2.1.  git fetch origin
2.2. git pull origin <latest-branch> (as of now, would be git pull origin manager)
      
Install any newly added dependencies
3.1. composer install --no-dev --optimize-autoloader
3.2. npm install 

Compile the frontend
 4.1. npm run build

Run any new database migrations
5.1.  php artisan migrate

Rebuild Laravel caches:
6.1. php artisan optimize

Restart the application services
7.1. sudo systemctl restart php8.2-fpm
7.2. sudo systemctl restart nginx 
7.3. sudo systemctl restart lstm-service  

System Verification
Verify the web server:
     	1.1 sudo systemctl status nginx 

Verify PHP-FPM:
     	2.1 sudo systemctl status php8.2-fpm 

Verify the LSTM service:
     	3.1. curl http://localhost:8001/

Review the Laravel log if any error occurs: 
4.1. tail -f storage/logs/laravel.log 

And for reviewing the Nginx log:
5.1.  sudo tail -f /var/log/nginx/error.log

     Successful deployment can be confirmed when:
Nginx and PHP-FPM services are active.
The Laravel application loads successfully through the web browser.
Database connectivity is established.
The LSTM forecasting service returns a healthy status.
Queue workers and scheduled tasks operate without errors.
No critical errors are recorded in the application or server logs.

# END-USER SYSTEM INSTALLATION 
The requirements and installation process from the perspective of the organization operating the KRB Receptionist System are covered in this section. For the web application to be accessible to all users in the organization, us as developers in charge must first deploy the application to the end-user domain being receptionistkebunraya.online.

 Hardware Requirements
              The production server is currently deployed on a laptop with the following minimum
             specifications:
 
Component
Specification
Laptop
ASUS N46VZ
CPU
Intel Core i7-3630QM CPU
RAM
4 GB
Storage
50 GB SSD
Operating System
Ubuntu Server 22.04 LTS
Public IP Address
1 Static IPv4 Address
Domain Name
receptionistkebunraya.online


             Software Requirements
             The following software components are for the server:
Component
Purpose
Ubuntu Server 22.04 LTS
Operating System
Nginx
Web Reserve / Reverse Proxy
PHP 8.2.
Laravel Runtime
Composer
Dependency Management
Node.js
Frontend Asset Compilation
MySQL / MariaDB
Database
Python 3.10+
AI Forecasting Service
Git
Version Control


   Network Configurations
	   The VPS firewall is configured to allow the following ports:
Port
Protocol
Function
80
TCP
HTTP Traffic
443
TCP
HTTPS Traffic
22
TCP
Secure Shell (SSH) Administration

  
   The internal application services communicate through the Ubuntu server’s networking and are not publicly exposed. 
   
   Domain Configuration
   The domain receptionistkebunraya.online is configured to point to the VPS public 
   IP address through DNS A Records.
    
               The DNS configuration is as follows:
                
Type
Host
Value
A
@
VPS Public IP
A
www
VPS Public IP


  After DNS propagation, users may access the system through:     
   receptionistkebunraya.online
