# Railway Deployment Guide for Akaunting SaaS

This guide will help you deploy Akaunting as a SaaS application on Railway.

## Prerequisites

1. **Railway Account**: Sign up at [railway.app](https://railway.app)
2. **GitHub Repository**: Your Akaunting code must be in a GitHub repository
3. **AWS S3 Bucket**: For file storage (receipts, invoices, etc.)
4. **Email Service**: SMTP credentials (Mailgun, SendGrid, etc.)

## Step 1: Prepare Your Repository

1. **Install modules locally** (if not already done):
   ```bash
   composer install
   git add modules/
   git commit -m "Add modules for Railway deployment"
   git push
   ```

2. **Verify files are present**:
   - `railway.json` ✅
   - `nixpacks.toml` ✅
   - `build.sh` ✅
   - `railway-start.sh` ✅
   - `.env.railway` ✅

## Step 2: Create Railway Project

1. Go to [railway.app](https://railway.app)
2. Click "New Project"
3. Select "Deploy from GitHub repo"
4. Choose your Akaunting repository
5. Railway will automatically detect it's a Laravel project

## Step 3: Add Required Services

### Add MySQL Database
1. In your Railway project, click "Add Service"
2. Select "Database" → "MySQL"
3. Railway will automatically create and configure the database

### Add Redis (for caching/sessions)
1. Click "Add Service" again
2. Select "Database" → "Redis"
3. Railway will automatically configure Redis

### Add Queue Worker (for background jobs)
1. Click "Add Service" → "Empty Service"
2. Name it "Queue Worker"
3. In settings, set:
   - **Build Command**: `composer install --no-dev --optimize-autoloader`
   - **Start Command**: `php artisan queue:work --sleep=3 --tries=3`
4. Add the same environment variables as your main app

## Step 4: Configure Environment Variables

In your main web service settings, add these environment variables:

### Required Variables
```env
APP_NAME=Your SaaS Name
APP_ENV=production
APP_DEBUG=false
APP_INSTALLED=false
APP_LOCALE=en-US
APP_SCHEDULE_TIME=09:00
```

### Database (Auto-configured by Railway)
Railway automatically sets:
- `MYSQLHOST`
- `MYSQLPORT`
- `MYSQLDATABASE`
- `MYSQLUSER`
- `MYSQLPASSWORD`

### File Storage (S3 - Required for SaaS)
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_s3_key
AWS_SECRET_ACCESS_KEY=your_s3_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
```

### Email Configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your_mailgun_username
MAIL_PASSWORD=your_mailgun_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME=Your SaaS Name
```

### Performance Settings
```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
LOG_LEVEL=error
OPCACHE_ENABLE=1
```

## Step 5: Deploy

1. Railway will automatically deploy when you push to your main branch
2. Monitor the build logs in Railway dashboard
3. First deployment will take 5-10 minutes

## Step 6: Post-Deployment Setup

### Initial Setup
1. Visit your Railway app URL
2. The first time, it will run the Akaunting installer
3. Default admin credentials:
   - Email: `admin@akaunting.com`
   - Password: `123456`
4. **Change these immediately after first login!**

### Configure Your Domain
1. In Railway project settings, go to "Domains"
2. Add your custom domain
3. Update `APP_URL` environment variable to your domain

### Set up SSL
Railway provides automatic SSL certificates for custom domains.

## Step 7: Configure S3 Bucket

### Create S3 Bucket
1. Go to AWS S3 Console
2. Create a new bucket (e.g., `your-saas-akaunting-files`)
3. Set region (match your `AWS_DEFAULT_REGION`)

### Configure Bucket Policy
Add this policy to allow public read access for invoices:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "PublicReadGetObject",
            "Effect": "Allow",
            "Principal": "*",
            "Action": "s3:GetObject",
            "Resource": "arn:aws:s3:::your-bucket-name/public/*"
        }
    ]
}
```

### Configure CORS
Add CORS configuration:

```json
[
    {
        "AllowedHeaders": ["*"],
        "AllowedMethods": ["GET", "PUT", "POST", "DELETE", "HEAD"],
        "AllowedOrigins": ["https://yourdomain.com"],
        "ExposeHeaders": []
    }
]
```

## Step 8: Monitoring & Maintenance

### Monitor Logs
- Check Railway dashboard for application logs
- Monitor database performance
- Watch for queue job failures

### Backup Strategy
1. **Database**: Set up automated MySQL backups in Railway
2. **Files**: S3 has built-in versioning and backup options
3. **Code**: Ensure your git repository is backed up

### Updates
1. Push updates to your GitHub repository
2. Railway will automatically deploy changes
3. Database migrations run automatically on deployment

## Troubleshooting

### Common Issues

1. **Modules Missing**
   - Ensure modules are committed to git
   - Check that `.gitignore` doesn't exclude modules

2. **Database Connection Failed**
   - Verify Railway database service is running
   - Check environment variables are set correctly

3. **File Upload Issues**
   - Verify S3 credentials are correct
   - Check bucket permissions and CORS settings

4. **Email Not Working**
   - Test SMTP credentials
   - Check spam folders
   - Verify DNS settings for custom domain

### Getting Help

1. Check Railway logs in the dashboard
2. Use `php artisan tinker` in Railway's terminal
3. Check Akaunting documentation
4. Railway has excellent community support

## Cost Estimation

Typical monthly costs for a small SaaS:
- **Web Service**: $5-20 (usage-based)
- **MySQL Database**: $5-15
- **Redis**: $5
- **Queue Worker**: $5-10
- **Total**: ~$20-50/month

Costs scale with usage, making it perfect for growing SaaS businesses.

## Security Checklist

- [ ] Change default admin credentials
- [ ] Set up custom domain with SSL
- [ ] Configure proper S3 bucket permissions
- [ ] Set `APP_DEBUG=false`
- [ ] Use strong `APP_KEY`
- [ ] Configure proper CORS settings
- [ ] Set up monitoring and alerts
- [ ] Regular security updates

## Next Steps

1. Set up customer onboarding flow
2. Configure payment processing
3. Set up analytics and monitoring
4. Plan scaling strategy
5. Set up automated backups

Your Akaunting SaaS is now ready for production! 🚀