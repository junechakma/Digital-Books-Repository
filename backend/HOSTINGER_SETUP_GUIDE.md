# 🚀 UCSI Digital Library - Complete Hostinger Setup Guide

## 📋 **Overview**

This guide covers the complete setup of your UCSI Digital Library system on Hostinger with:
- ✅ **Cart-based download system** (students add multiple books to cart)
- ✅ **Single OTP verification** (one OTP to download entire cart)
- ✅ **Admin panel with OTP password recovery**
- ✅ **Comprehensive logging** (who uploaded what, when, login tracking)
- ✅ **Professional email system** (Hostinger SMTP)

---

## 🌐 **Hostinger Email Setup**

### **Step 1: Create Email Account**
1. **Login to Hostinger Panel**
2. **Go to Email > Email Accounts**
3. **Create email**: `library@yourdomain.com`
4. **Set strong password**
5. **Note the SMTP settings**:
   - **Host**: `smtp.hostinger.com`
   - **Port**: `587` (STARTTLS) or `465` (SSL)
   - **Username**: `library@yourdomain.com`
   - **Password**: [your email password]

### **Step 2: Configure Email in System**
Update these settings in your database:

```sql
UPDATE system_settings SET setting_value = 'smtp.hostinger.com' WHERE setting_key = 'smtp_host';
UPDATE system_settings SET setting_value = '587' WHERE setting_key = 'smtp_port';
UPDATE system_settings SET setting_value = 'library@yourdomain.com' WHERE setting_key = 'smtp_username';
UPDATE system_settings SET setting_value = 'your_email_password' WHERE setting_key = 'smtp_password';
UPDATE system_settings SET setting_value = 'library@yourdomain.com' WHERE setting_key = 'email_from_address';
UPDATE system_settings SET setting_value = 'UCSI Digital Library' WHERE setting_key = 'email_from_name';
```

---

## 📁 **File Structure on Hostinger**

```
public_html/
├── api/                           # Backend API
│   ├── config/
│   │   ├── database.php          # DB connection
│   │   ├── config.php            # Global config
│   │   └── email.php             # Email service
│   ├── classes/
│   │   ├── Book.php              # Book management
│   │   ├── CartSystem.php        # Cart functionality
│   │   ├── StudentVerification.php # OTP system
│   │   ├── AdminAuth.php         # Admin authentication
│   │   └── AdminLogger.php       # Activity logging
│   ├── endpoints/
│   │   ├── books.php             # Book CRUD
│   │   ├── cart.php              # Cart operations
│   │   ├── cart_download.php     # Cart download OTP
│   │   ├── cart_download_files.php # File serving
│   │   ├── verify_download.php   # Single book OTP
│   │   └── upload.php            # File uploads
│   └── .htaccess                 # URL rewriting
├── uploads/
│   ├── books/                    # PDF storage
│   └── covers/                   # Cover images
├── your-react-app/               # Frontend files
└── index.php                     # Optional landing page
```

---

## 🗄️ **Database Setup**

### **Step 1: Create Database**
1. **Login to Hostinger Panel**
2. **Go to Databases > MySQL**
3. **Create database**: `ucsi_digital_library`
4. **Create user** with full permissions
5. **Note credentials**

### **Step 2: Import Schema**
1. **Go to phpMyAdmin**
2. **Select your database**
3. **Import** `backend/database/final_schema.sql`

### **Step 3: Update Database Config**
Edit `api/config/database.php`:

```php
private $host = 'localhost';
private $db_name = 'your_db_name';
private $username = 'your_db_user';
private $password = 'your_db_password';
```

---

## 🔄 **Complete Cart Download Flow**

### **Student Journey:**

1. **Browse Books** → Student browses library (no login required)
2. **Add to Cart** → Student adds multiple books to cart (session-based)
3. **Start Download** → Student enters UCSI email (`12345@ucsiuniversity.edu.my`)
4. **Receive OTP** → System sends 6-digit code to email
5. **Verify OTP** → Student enters code, gets download link
6. **Download ZIP** → All books downloaded as single ZIP file
7. **Cart Cleared** → Cart automatically cleared after download

### **API Flow:**

```javascript
// 1. Add books to cart
POST /api/cart
{
  "action": "add_to_cart",
  "session_id": "browser_session_id",
  "book_id": 123
}

// 2. Initialize cart download
POST /api/cart_download
{
  "action": "initialize_download",
  "session_id": "browser_session_id",
  "student_email": "12345@ucsiuniversity.edu.my"
}

// 3. Verify OTP
POST /api/cart_download
{
  "action": "verify_otp",
  "download_session_id": "session_id_from_step_2",
  "otp_code": "123456"
}

// 4. Download files
GET /api/cart_download_files?token=download_token
```

---

## 👨‍💼 **Admin Features**

### **Login with OTP Recovery:**

1. **Forgot Password** → Admin enters email
2. **Receive OTP** → System sends recovery code
3. **Reset Password** → Admin enters OTP + new password
4. **Security Logging** → All actions logged automatically

### **Complete Activity Logging:**

```sql
-- View admin activities
SELECT * FROM admin_logs ORDER BY created_at DESC;

-- View login attempts
SELECT * FROM admin_logs WHERE action LIKE '%login%';

-- View book management actions
SELECT * FROM admin_logs WHERE entity_type = 'book';

-- View file uploads
SELECT * FROM admin_logs WHERE action = 'upload_file';
```

### **Book Management:**
- **Create books** → Matches your BookForm.tsx exactly
- **Upload PDFs** → Secure file storage
- **Track everything** → Who uploaded what, when

---

## 📧 **Email Templates**

### **Cart Download OTP Email:**
- **Subject**: "UCSI Digital Library - Download Verification for X Books"
- **Content**: Professional HTML template with:
  - Student ID verification
  - List of books in cart
  - 6-digit OTP code
  - Security instructions
  - UCSI branding

### **Admin Password Reset Email:**
- **Subject**: "UCSI Digital Library - Admin Password Reset Code"
- **Content**: High-priority security template with:
  - Admin name personalization
  - Security warnings
  - Password strength guidelines
  - Contact information

---

## 🔒 **Security Features**

### **Student Verification:**
- ✅ Only `@ucsiuniversity.edu.my` emails allowed
- ✅ OTP expires in 10 minutes
- ✅ Rate limiting (2-minute cooldown)
- ✅ Download tracking with IP/browser info

### **Admin Security:**
- ✅ Account lockout after 5 failed attempts
- ✅ Password strength requirements
- ✅ Session management
- ✅ Activity logging for everything
- ✅ OTP-based password recovery

### **File Security:**
- ✅ PDF type validation
- ✅ File size limits (50MB)
- ✅ Secure filename generation
- ✅ Protected upload directories

---

## 🧪 **Testing Your Setup**

### **1. Test Email Configuration**
```bash
curl -X POST "https://yourdomain.com/api/test_email" \
  -H "Content-Type: application/json" \
  -d '{"test_email": "your_email@gmail.com"}'
```

### **2. Test Cart System**
```bash
# Add book to cart
curl -X POST "https://yourdomain.com/api/cart" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_to_cart",
    "session_id": "test_session_123",
    "book_id": 1
  }'

# Get cart contents
curl "https://yourdomain.com/api/cart?action=get_cart&session_id=test_session_123"
```

### **3. Test Student Download**
```bash
# Initialize download
curl -X POST "https://yourdomain.com/api/cart_download" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "initialize_download",
    "session_id": "test_session_123",
    "student_email": "12345@ucsiuniversity.edu.my"
  }'
```

### **4. Test Admin Features**
```bash
# Test admin login
curl -X POST "https://yourdomain.com/api/admin/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@ucsi.edu.my",
    "password": "admin123"
  }'

# Request password reset
curl -X POST "https://yourdomain.com/api/admin/reset_password" \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@ucsi.edu.my"}'
```

---

## 📊 **Monitoring & Analytics**

### **View Download Statistics:**
```sql
-- Downloads by date
SELECT DATE(download_date) as date, COUNT(*) as downloads
FROM downloads
GROUP BY DATE(download_date)
ORDER BY date DESC;

-- Most popular books
SELECT b.title, COUNT(d.id) as download_count
FROM books b
LEFT JOIN downloads d ON b.id = d.book_id
GROUP BY b.id
ORDER BY download_count DESC;

-- Cart usage statistics
SELECT
  COUNT(DISTINCT session_id) as active_carts,
  COUNT(*) as total_items,
  AVG(items_per_cart) as avg_items_per_cart
FROM (
  SELECT session_id, COUNT(*) as items_per_cart
  FROM student_carts
  WHERE expires_at > NOW()
  GROUP BY session_id
) as cart_stats;
```

### **View Admin Activity:**
```sql
-- Admin actions summary
SELECT admin_email, action, COUNT(*) as count
FROM admin_logs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY admin_email, action
ORDER BY count DESC;
```

---

## 🚀 **Go Live Checklist**

### **Before Launch:**
- [ ] Database imported and configured
- [ ] Email settings tested and working
- [ ] Upload directories created with proper permissions
- [ ] Admin account created and tested
- [ ] Sample books uploaded and tested
- [ ] Cart system tested end-to-end
- [ ] OTP emails working correctly
- [ ] Download system tested
- [ ] Frontend connected to API
- [ ] SSL certificate installed
- [ ] Domain DNS configured

### **After Launch:**
- [ ] Monitor error logs daily
- [ ] Check email delivery rates
- [ ] Monitor download statistics
- [ ] Regular database backups
- [ ] Update admin passwords regularly
- [ ] Clean up expired carts weekly

---

## 🆘 **Troubleshooting**

### **Common Issues:**

1. **"Email not sending"**
   - Check SMTP credentials in database
   - Verify Hostinger email account is active
   - Test with simple PHP mail() first

2. **"Cart not working"**
   - Check session ID generation in frontend
   - Verify database table creation
   - Check browser cookies/local storage

3. **"Download fails"**
   - Verify PDF files exist in upload directory
   - Check file permissions (755 for directories, 644 for files)
   - Monitor server error logs

4. **"Admin can't login"**
   - Check password hash in database
   - Verify account is not locked
   - Check admin_logs for failed attempts

### **Log Locations:**
- **API Logs**: `backend/logs/api.log`
- **Server Logs**: Check Hostinger control panel
- **Database Logs**: Via phpMyAdmin or direct MySQL access

---

## 📞 **Support & Maintenance**

### **Regular Maintenance:**
- **Weekly**: Clean up expired carts and OTPs
- **Monthly**: Review admin activity logs
- **Quarterly**: Update passwords and review security

### **Performance Optimization:**
- Monitor database query performance
- Optimize file storage structure
- Consider CDN for large PDF files
- Regular database maintenance

---

Your **UCSI Digital Library** system is now fully configured for professional use on Hostinger! 🎉

**Key Features Delivered:**
- ✅ **Cart-based downloads** with single OTP verification
- ✅ **Professional email system** with Hostinger SMTP
- ✅ **Complete admin logging** and security
- ✅ **Scalable architecture** ready for growth
- ✅ **UCSI-specific email validation**
- ✅ **Comprehensive error handling** and monitoring