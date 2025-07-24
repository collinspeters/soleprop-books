# Railway Deployment Checklist for Akaunting SaaS

Use this checklist to ensure your Railway deployment is ready for production.

## ✅ Pre-Deployment (Local Setup)

### Code Preparation
- [ ] **Modules are installed and committed**
  ```bash
  composer install
  ls -la modules/ # Should show OfflinePayments and PaypalStandard
  git add modules/
  git commit -m "Add modules for deployment"
  ```

- [ ] **All deployment files are present**
  - [ ] `railway.json` 
  - [ ] `nixpacks.toml`
  - [ ] `build.sh` (executable)
  - [ ] `railway-start.sh` (executable)
  - [ ] `RAILWAY_DEPLOYMENT.md`

- [ ] **Environment files are configured**
  - [ ] `.env.railway` exists
  - [ ] `.env.production` template exists
  - [ ] Production env files are in `.gitignore`

- [ ] **Code is pushed to GitHub**
  ```bash
  git push origin main
  ```

## ✅ Railway Project Setup

### Project Creation
- [ ] **Railway account created** at [railway.app](https://railway.app)
- [ ] **GitHub repository connected**
- [ ] **Project created from GitHub repo**
- [ ] **Laravel framework detected automatically**

### Services Configuration
- [ ] **Main web service** configured
  - [ ] Build command: `./build.sh`
  - [ ] Start command: `./railway-start.sh`
  - [ ] Health check: `/health`

- [ ] **MySQL database** added
  - [ ] Service created
  - [ ] Environment variables auto-configured

- [ ] **Redis cache** added
  - [ ] Service created
  - [ ] Environment variables auto-configured

- [ ] **Queue worker** added (optional but recommended)
  - [ ] Empty service created
  - [ ] Start command: `php artisan queue:work --sleep=3 --tries=3`
  - [ ] Same environment variables as main app

## ✅ Environment Variables Setup

### Application Settings
- [ ] `APP_NAME` = Your SaaS name
- [ ] `APP_ENV` = production
- [ ] `APP_DEBUG` = false
- [ ] `APP_INSTALLED` = false (for first deployment)
- [ ] `APP_LOCALE` = en-US
- [ ] `APP_URL` = Your Railway domain
- [ ] `APP_KEY` = Generated key (run `php artisan key:generate`)

### Database (Auto-configured by Railway)
- [ ] `DB_CONNECTION` = mysql
- [ ] `DB_HOST` = ${MYSQLHOST}
- [ ] `DB_PORT` = ${MYSQLPORT}
- [ ] `DB_DATABASE` = ${MYSQLDATABASE}
- [ ] `DB_USERNAME` = ${MYSQLUSER}
- [ ] `DB_PASSWORD` = ${MYSQLPASSWORD}

### Cache & Performance
- [ ] `CACHE_DRIVER` = redis
- [ ] `SESSION_DRIVER` = redis
- [ ] `QUEUE_CONNECTION` = redis
- [ ] `REDIS_URL` = ${REDIS_URL}

### File Storage (S3 Required)
- [ ] `FILESYSTEM_DISK` = s3
- [ ] `AWS_ACCESS_KEY_ID` = Your S3 key
- [ ] `AWS_SECRET_ACCESS_KEY` = Your S3 secret
- [ ] `AWS_DEFAULT_REGION` = Your region
- [ ] `AWS_BUCKET` = Your bucket name

### Email Configuration
- [ ] `MAIL_MAILER` = smtp
- [ ] `MAIL_HOST` = Your SMTP host
- [ ] `MAIL_PORT` = 587
- [ ] `MAIL_USERNAME` = Your SMTP username
- [ ] `MAIL_PASSWORD` = Your SMTP password
- [ ] `MAIL_FROM_ADDRESS` = Your from email
- [ ] `MAIL_FROM_NAME` = Your SaaS name

## ✅ AWS S3 Setup

### Bucket Configuration
- [ ] **S3 bucket created**
  - [ ] Bucket name matches `AWS_BUCKET`
  - [ ] Region matches `AWS_DEFAULT_REGION`
  - [ ] Versioning enabled (recommended)

- [ ] **IAM user created** with S3 permissions
  - [ ] Access key generated
  - [ ] Secret key generated
  - [ ] Permissions: s3:GetObject, s3:PutObject, s3:DeleteObject

- [ ] **Bucket policy configured** for public read access
  ```json
  {
    "Version": "2012-10-17",
    "Statement": [
      {
        "Effect": "Allow",
        "Principal": "*",
        "Action": "s3:GetObject",
        "Resource": "arn:aws:s3:::your-bucket/*"
      }
    ]
  }
  ```

- [ ] **CORS configuration** added
  ```json
  [
    {
      "AllowedHeaders": ["*"],
      "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
      "AllowedOrigins": ["https://yourdomain.com"],
      "ExposeHeaders": []
    }
  ]
  ```

## ✅ Email Service Setup

### SMTP Provider (Choose One)
- [ ] **Mailgun**
  - [ ] Domain verified
  - [ ] SMTP credentials obtained
  - [ ] DNS records configured

- [ ] **SendGrid**
  - [ ] Account created
  - [ ] API key generated
  - [ ] Sender identity verified

- [ ] **Amazon SES**
  - [ ] Domain verified
  - [ ] SMTP credentials obtained
  - [ ] Out of sandbox mode

## ✅ First Deployment

### Deploy Process
- [ ] **Push to trigger deployment**
  ```bash
  git push origin main
  ```

- [ ] **Monitor build logs** in Railway dashboard
  - [ ] Build completes successfully
  - [ ] No error messages
  - [ ] All services start

- [ ] **Health check passes**
  - [ ] `/health` endpoint responds
  - [ ] Status shows "ok"

### Initial Setup
- [ ] **Visit application URL**
- [ ] **Akaunting installer runs** (first time only)
- [ ] **Default admin login works**
  - Email: admin@akaunting.com
  - Password: 123456

- [ ] **Change admin credentials immediately**
  - [ ] New email address
  - [ ] Strong password
  - [ ] Security questions

## ✅ Post-Deployment Verification

### Core Functionality
- [ ] **Dashboard loads** without errors
- [ ] **Database connection** working
- [ ] **File uploads** work (test with S3)
- [ ] **Email sending** works (test forgot password)
- [ ] **Invoice generation** works
- [ ] **Receipt upload** works (OCR feature)
- [ ] **Bank feeds** can be configured

### Performance & Security
- [ ] **SSL certificate** active
- [ ] **Custom domain** configured (if applicable)
- [ ] **Cache working** (Redis)
- [ ] **Queue processing** working
- [ ] **Logs are clean** (no errors)

### Features Testing
- [ ] **Create company** works
- [ ] **Add customers** works
- [ ] **Create invoices** works
- [ ] **Process payments** works
- [ ] **Upload receipts** works
- [ ] **Generate reports** works

## ✅ Production Hardening

### Security
- [ ] **APP_DEBUG** = false
- [ ] **Strong APP_KEY** generated
- [ ] **HTTPS enforced**
- [ ] **Secure cookies** enabled
- [ ] **Default credentials** changed
- [ ] **File upload limits** configured

### Monitoring
- [ ] **Error tracking** set up (Sentry, Bugsnag)
- [ ] **Uptime monitoring** configured
- [ ] **Performance monitoring** enabled
- [ ] **Log aggregation** set up

### Backup Strategy
- [ ] **Database backups** automated
- [ ] **S3 versioning** enabled
- [ ] **Code repository** backed up
- [ ] **Recovery procedure** documented

## ✅ Go-Live Checklist

### Final Verification
- [ ] **All features tested** end-to-end
- [ ] **Performance acceptable** under load
- [ ] **Security scan** completed
- [ ] **Backup/restore** tested
- [ ] **Documentation** complete

### Marketing Preparation
- [ ] **Landing page** ready
- [ ] **Pricing page** configured
- [ ] **Support documentation** available
- [ ] **Payment processing** integrated
- [ ] **Analytics** configured

### Launch
- [ ] **DNS updated** to production domain
- [ ] **SSL certificate** verified
- [ ] **Monitoring alerts** configured
- [ ] **Team notified** of go-live
- [ ] **Support channels** ready

## 🚨 Troubleshooting

### Common Issues
- **Build fails**: Check `build.sh` permissions and syntax
- **Database connection fails**: Verify Railway DB service is running
- **File uploads fail**: Check S3 credentials and bucket permissions
- **Emails not sending**: Verify SMTP credentials and DNS
- **Modules missing**: Ensure modules are committed to git

### Getting Help
- Railway documentation: https://docs.railway.app
- Railway community: https://discord.gg/railway
- Akaunting documentation: https://akaunting.com/docs
- GitHub issues: Your repository issues page

---

**🎉 Congratulations!** Your Akaunting SaaS is now live on Railway!

Remember to:
- Monitor performance and errors
- Keep dependencies updated
- Regular security audits
- Scale resources as needed
- Engage with your users for feedback