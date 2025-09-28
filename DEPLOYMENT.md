# Biddart SAAS - Cloudways Deployment Guide

## 🚀 Cloudways Deployment Steps

### **1. Prepare Cloudways Server**

#### **Server Requirements:**
- **PHP Version**: 8.2 or higher
- **Database**: MySQL 8.0+
- **Memory**: Minimum 2GB RAM (4GB+ recommended for production)
- **Storage**: SSD with at least 20GB free space

#### **Cloudways Server Setup:**
1. Create new server on Cloudways
2. Choose **PHP 8.2** application
3. Select appropriate server size (2GB+ RAM)
4. Choose your preferred cloud provider (DigitalOcean, AWS, etc.)

### **2. Configure Domain and SSL**

#### **Domain Setup:**
1. Point your domain to Cloudways server IP
2. Add domain in Cloudways panel
3. Enable SSL certificate (Let's Encrypt)
4. Configure wildcard subdomain: `*.yourdomain.com`

#### **Subdomain Configuration:**
For multi-tenant subdomains, ensure your DNS has:
```
A    yourdomain.com        → Server IP
A    *.yourdomain.com      → Server IP
```

### **3. Database Setup**

#### **Create Database:**
1. Go to Cloudways → Application → Database
2. Create new database: `biddart_saas`
3. Note down database credentials
4. Ensure UTF8MB4 charset for emoji support

### **4. Deploy Application**

#### **Method 1: Git Deployment (Recommended)**
```bash
# SSH into your Cloudways server
cd /home/master/applications/your-app/public_html

# Clone repository
git clone https://github.com/yourusername/biddart-saas.git .

# Install dependencies
composer install --optimize-autoloader --no-dev

# Install Node dependencies and build assets
npm install
npm run build
```

#### **Method 2: File Upload**
1. Zip your project (excluding vendor/, node_modules/, .env)
2. Upload via Cloudways File Manager
3. Extract in public_html directory

### **5. Environment Configuration**

#### **Setup Environment File:**
```bash
# Copy production environment template
cp .env.cloudways .env

# Generate application key
php artisan key:generate

# Edit .env with your actual values
nano .env
```

#### **Required Environment Variables:**
```env
APP_URL=https://yourdomain.com
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
SESSION_DOMAIN=.yourdomain.com

# Add your production API keys
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
SQUARE_APPLICATION_ID=your_square_app_id
SQUARE_ACCESS_TOKEN=your_square_access_token
```

### **6. Run Migrations and Setup**

```bash
# Run database migrations
php artisan migrate --force

# Create storage symlink
php artisan storage:link

# Cache configuration for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set proper permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### **7. Create Demo Data (Optional)**

```bash
# Seed demo tenant and data
php artisan db:seed --class=TenantSeeder

# Or create tenant manually
php artisan tenant:create "Your Organization Name" --admin-email=admin@yourorg.com --admin-password=secure_password
```

### **8. Configure Cloudways Settings**

#### **PHP Settings:**
- **Memory Limit**: 512M or higher
- **Max Execution Time**: 300 seconds
- **Upload Max Filesize**: 64M
- **Post Max Size**: 64M

#### **Cron Jobs:**
Add to Cloudways Cron Jobs:
```bash
# Laravel Scheduler (every minute)
* * * * * cd /home/master/applications/your-app/public_html && php artisan schedule:run >> /dev/null 2>&1

# Queue Worker (if using queues)
* * * * * cd /home/master/applications/your-app/public_html && php artisan queue:work --stop-when-empty
```

### **9. Security Configuration**

#### **Cloudways Security:**
1. Enable **Two-Factor Authentication**
2. Configure **IP Whitelisting** for SSH
3. Enable **Bot Protection**
4. Set up **Regular Backups**

#### **Application Security:**
```bash
# Set secure file permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 777 storage bootstrap/cache
```

### **10. Performance Optimization**

#### **Cloudways Optimizations:**
1. Enable **Redis** (if available)
2. Configure **Varnish Cache**
3. Enable **Cloudflare** integration
4. Set up **CDN** for assets

#### **Laravel Optimizations:**
```bash
# Optimize for production
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Enable OPcache in PHP settings
```

## 🔧 Post-Deployment Configuration

### **1. Test Multi-Tenancy**
- Visit main domain: `https://yourdomain.com`
- Test subdomain: `https://demo-nonprofit.yourdomain.com`
- Verify tenant isolation works correctly

### **2. Configure Payment Processing**
- Set up Square production credentials
- Test payment flow end-to-end
- Verify platform fee calculations

### **3. Configure SMS Verification**
- Set up Twilio production account
- Test shared credential login flow
- Verify SMS delivery

### **4. Set Up Monitoring**
- Configure error logging
- Set up uptime monitoring
- Monitor database performance
- Track revenue metrics

## 🚨 Troubleshooting

### **Common Issues:**

#### **Subdomain Not Working:**
```bash
# Check DNS propagation
nslookup subdomain.yourdomain.com

# Verify Apache/Nginx configuration
# Cloudways usually handles this automatically
```

#### **Database Connection Issues:**
```bash
# Test database connection
php artisan tinker
DB::connection()->getPdo();
```

#### **Permission Issues:**
```bash
# Fix Laravel permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

#### **Asset Loading Issues:**
```bash
# Rebuild assets
npm run build
php artisan view:clear
```

## 📊 Production Checklist

- [ ] Domain and SSL configured
- [ ] Database created and migrated
- [ ] Environment variables set
- [ ] File permissions configured
- [ ] Cron jobs scheduled
- [ ] Backups configured
- [ ] Monitoring set up
- [ ] Square API configured (production)
- [ ] Twilio SMS configured
- [ ] Email service configured
- [ ] Error logging enabled
- [ ] Performance optimization applied
- [ ] Security settings enabled
- [ ] Multi-tenancy tested
- [ ] Payment flow tested
- [ ] Demo data created (optional)

## 🎯 Go-Live Steps

1. **Final Testing**: Test all features thoroughly
2. **DNS Switch**: Point production domain to Cloudways
3. **SSL Verification**: Ensure SSL works on all subdomains
4. **Monitor**: Watch logs and performance metrics
5. **Backup**: Take full backup before going live

Your Biddart SAAS application is now ready for production! 🚀
