# SkillVerge - Complete AI Interview Platform

## 🚀 Quick Setup

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Internet connection for payment gateways

### Installation Steps

1. **Download/Clone the project**
   \`\`\`bash
   git clone <repository-url>
   cd skillverge-portal
   \`\`\`

2. **Database Setup**
   - Create a MySQL database named `skillverge`
   - Import the database schema:
     \`\`\`bash
     mysql -u username -p skillverge < scripts/skillverge.sql
     \`\`\`

3. **Configure Database Connection**
   - Update `config/database.php` with your database credentials:
     \`\`\`php
     private $host = "localhost";
     private $db_name = "skillverge";
     private $username = "your_username";
     private $password = "your_password";
     \`\`\`

4. **Set File Permissions**
   \`\`\`bash
   chmod 755 uploads/
   chmod 755 recordings/
   chmod 755 assets/images/
   \`\`\`

5. **Access the Platform**
   - Main site: `http://your-domain/`
   - Login with default credentials (see below)

## 📁 Project Structure

\`\`\`
skillverge-portal/
├── config/
│   ├── database.php          # Database configuration
│   └── auth.php             # Authentication settings
├── scripts/
│   └── skillverge.sql       # Complete database setup
├── candidate/               # Candidate dashboard & features
│   ├── dashboard.php        # Main candidate dashboard
│   ├── ai-interview.php     # AI-powered mock interviews
│   ├── payment.php          # Payment processing
│   ├── job-board.php        # Job listings and applications
│   └── verify-upi-payment.php # UPI payment verification
├── company/                # Company features
│   ├── dashboard.php        # Company dashboard
│   └── create-job.php       # Job posting management
├── expert/                 # Expert interview features
│   ├── mock-interview-room.php # Expert interview room
│   └── save-expert-notes.php   # Expert feedback system
├── admin/                  # Admin panel
│   ├── dashboard.php        # Admin dashboard
│   ├── system-settings.php  # System configuration
│   └── analytics-dashboard.php # Analytics & reports
├── api/                    # API endpoints
│   ├── ai-analysis.php      # AI interview analysis
│   ├── execute-code.php     # Code execution engine
│   └── generate-candidate-brief.php # Expert briefing
├── utils/                  # Utility functions
│   ├── email-service.php    # Email notifications
│   ├── notification-service.php # In-app notifications
│   └── code-executor.php    # Code execution utilities
├── assets/                 # Frontend resources
│   ├── css/style.css        # Main stylesheet
│   ├── js/main.js          # Core JavaScript
│   ├── js/ai-interview.js   # AI interview functionality
│   └── images/             # Images and assets
├── ai_engine/              # Python AI analysis
│   └── interview_analyzer.py # AI scoring engine
└── index.html              # Landing page
\`\`\`

## 🔐 Default Login Credentials

**Admin Account:**
- Email: `admin@skillverge.com`
- Password: `password`

**Test Candidate:**
- Email: `candidate@example.com`
- Password: `password`

**Test Company:**
- Email: `company@example.com`
- Password: `password`

**Test Expert:**
- Email: `expert@example.com`
- Password: `password`

⚠️ **Important:** Change all default passwords after first login!

## 💳 Payment Integration

### Supported Payment Methods

#### 1. Razorpay Integration
- Credit/Debit Cards
- Net Banking
- UPI via Razorpay
- Digital Wallets

#### 2. Direct UPI Payments
- **QR Code Scanning** - Users can scan the provided QR code
- **UPI ID Payments** - Direct transfer to:
  - `9693939756@ibl`
  - `9693939756@ybl`
  - `9693939756@axl`

### Payment Configuration
1. **Razorpay Setup:**
   - Login as admin
   - Go to System Settings
   - Add your Razorpay Key ID and Secret

2. **UPI Payments:**
   - QR code is pre-configured
   - UPI IDs are ready for use
   - Manual verification system included

## 🎯 Core Features

### 🤖 AI-Powered Interviews
- Real-time speech recognition and analysis
- Automated scoring based on multiple factors
- Instant feedback and improvement suggestions
- Industry-specific question banks
- Performance benchmarking

### 👨‍💼 Expert Interview System
- Professional mock interview scheduling
- Real-time candidate evaluation
- AI-generated candidate briefs for experts
- Structured feedback and scoring
- Video interview capabilities

### 💼 Job Board & Applications
- Company job posting management
- Candidate application tracking
- AI-powered job recommendations
- Application status notifications
- Interview scheduling integration

### 📊 Analytics & Reporting
- Comprehensive performance analytics
- Payment transaction reports
- User engagement metrics
- System health monitoring
- Custom report generation

### 🔔 Notification System
- Real-time in-app notifications
- Email notification templates
- SMS integration ready
- Push notification support
- Customizable notification preferences

## 🛠 Advanced Features

### Code Execution Engine
- Multi-language support (Python, JavaScript, Java, C++)
- Secure sandboxed execution environment
- Real-time code testing during interviews
- Automated code quality assessment

### Resume & Skill Assessment
- Automated resume parsing
- Skill gap analysis
- Industry benchmarking
- Competency mapping
- Progress tracking

### Video Interview Integration
- Jitsi Meet integration
- Screen sharing capabilities
- Interview recording
- Real-time collaboration tools
- Mobile-friendly interface

## 🔧 Configuration

### Email Configuration
Edit `utils/email-service.php`:
\`\`\`php
$mail->Host = 'your-smtp-host';
$mail->Username = 'your-email@domain.com';
$mail->Password = 'your-email-password';
$mail->Port = 587; // or 465 for SSL
\`\`\`

### System Settings
Configure through Admin Panel:
- Payment gateway settings
- Email templates
- Interview question banks
- Scoring algorithms
- User role permissions

## 📱 Mobile Responsiveness

SkillVerge is fully responsive and optimized for:
- 📱 **Mobile Phones** (320px - 767px)
- 📱 **Tablets** (768px - 1199px)
- 💻 **Desktops** (1200px+)

### Mobile Features
- Touch-optimized interface
- Responsive navigation
- Mobile-friendly forms
- Optimized loading times
- Gesture support

## 🔒 Security Features

### Authentication & Authorization
- Multi-role user management
- Session-based authentication
- Password encryption (bcrypt)
- Role-based access control
- Account lockout protection

### Data Security
- SQL injection prevention
- XSS protection
- CSRF token validation
- Input sanitization
- Secure file uploads

### Audit & Monitoring
- Comprehensive audit logging
- User activity tracking
- Security event monitoring
- Performance metrics
- Error logging

## 🛠 Troubleshooting

### Common Issues

#### Database Connection Issues
1. Verify MySQL service is running
2. Check credentials in `config/database.php`
3. Ensure database user has proper permissions
4. Test connection manually

#### Permission Issues
\`\`\`bash
sudo chown -R www-data:www-data /path/to/skillverge
sudo chmod -R 755 /path/to/skillverge
sudo chmod -R 777 uploads/ recordings/
\`\`\`

#### Email Not Working
1. Check SMTP settings in `utils/email-service.php`
2. Verify firewall allows SMTP ports (587/465)
3. Test with Gmail SMTP first
4. Check spam folders

#### Payment Issues
1. Verify Razorpay credentials in admin settings
2. Check UPI ID format and availability
3. Test in sandbox mode first
4. Monitor payment logs

### Performance Optimization
\`\`\`bash
# Enable PHP OPcache
echo "opcache.enable=1" >> /etc/php/php.ini

# MySQL optimization
echo "query_cache_size = 64M" >> /etc/mysql/my.cnf
echo "innodb_buffer_pool_size = 256M" >> /etc/mysql/my.cnf
\`\`\`

## 📊 Database Information

### Main Tables
- `users` - All user accounts and authentication
- `candidates` - Candidate profiles and preferences
- `companies` - Company profiles and settings
- `experts` - Expert profiles and availability
- `ai_interviews` - AI interview sessions and results
- `expert_interviews` - Expert interview bookings
- `job_postings` - Job listings and requirements
- `job_applications` - Application tracking
- `payments` - Payment transactions and history
- `notifications` - System notifications
- `system_settings` - Platform configuration

### Database Maintenance
\`\`\`bash
# Backup
mysqldump -u username -p skillverge > backup_$(date +%Y%m%d).sql

# Restore
mysql -u username -p skillverge < backup_file.sql

# Optimize
mysql -u username -p -e "OPTIMIZE TABLE skillverge.*"
\`\`\`

## 🚀 Production Deployment

### Pre-Deployment Checklist
- [ ] Change all default passwords
- [ ] Configure SSL certificate
- [ ] Set up proper file permissions
- [ ] Configure email settings
- [ ] Test payment gateways
- [ ] Set up backup system
- [ ] Configure monitoring
- [ ] Update database credentials
- [ ] Test all user flows

### Server Requirements
- **PHP**: 7.4+ with extensions (mysqli, curl, gd, mbstring)
- **MySQL**: 5.7+ or MariaDB 10.2+
- **Web Server**: Apache 2.4+ or Nginx 1.14+
- **Memory**: Minimum 512MB RAM
- **Storage**: Minimum 2GB free space
- **SSL**: Required for production

### Performance Monitoring
- Set up database monitoring
- Configure error alerting
- Monitor disk space usage
- Track response times
- Regular security audits

## 🔄 Updates & Maintenance

### Regular Maintenance Tasks
- Weekly database backups
- Monthly security updates
- Quarterly performance reviews
- Annual security audits
- Regular log cleanup

### Update Process
1. Backup current system
2. Test updates in staging
3. Apply updates during maintenance window
4. Verify all functionality
5. Monitor for issues

## 📞 Support & Documentation

### Getting Help
1. Check this README for common solutions
2. Review error logs in `/var/log/` or server logs
3. Verify all prerequisites are met
4. Test with default credentials first

### System Logs
- **Application Logs**: Check PHP error logs
- **Database Logs**: Monitor MySQL slow query log
- **Web Server Logs**: Review Apache/Nginx access logs
- **Payment Logs**: Check Razorpay dashboard

## 📄 License & Credits

### License
This project is proprietary software. All rights reserved.

### Third-Party Libraries
- Bootstrap 5 - UI Framework
- jQuery - JavaScript Library
- Razorpay - Payment Gateway
- Jitsi Meet - Video Conferencing
- Chart.js - Analytics Charts
- PHPMailer - Email Service

### AI Engine
- Python-based analysis engine
- Natural language processing
- Speech recognition integration
- Machine learning algorithms

---

## 🎉 Quick Start Guide

1. **Import Database**: `mysql -u user -p skillverge < scripts/skillverge.sql`
2. **Configure Database**: Update `config/database.php`
3. **Set Permissions**: `chmod 755 uploads/ recordings/`
4. **Access Platform**: Open in browser
5. **Login as Admin**: admin@skillverge.com / password
6. **Configure Settings**: Set up payments and email
7. **Start Using**: Create accounts and begin interviews!

**SkillVerge is now ready for production use!** 🚀
