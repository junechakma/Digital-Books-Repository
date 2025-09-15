# UCSI Digital Library - PHP Backend API

Complete PHP backend implementation for the UCSI Digital Library system with MySQL database storage for PDFs and metadata.

## 📁 Directory Structure

```
backend/
├── api/
│   ├── config/
│   │   ├── database.php     # Database connection config
│   │   └── config.php       # Global configuration
│   ├── classes/
│   │   ├── Book.php         # Book model with CRUD operations
│   │   ├── Download.php     # Download tracking model
│   │   └── FileUpload.php   # Secure file upload handler
│   ├── endpoints/
│   │   ├── books.php        # Books CRUD API
│   │   ├── upload.php       # File upload API
│   │   ├── download.php     # Secure PDF download
│   │   └── search.php       # Enhanced search API
│   └── .htaccess           # Apache routing & security
├── database/
│   └── schema.sql          # MySQL database schema
├── uploads/
│   ├── books/              # PDF files storage
│   └── covers/             # Cover images storage
└── logs/                   # API logs (auto-created)
```

## 🚀 XAMPP Setup Instructions

### 1. Database Setup

1. **Start XAMPP**: Start Apache and MySQL services
2. **Open phpMyAdmin**: Go to `http://localhost/phpmyadmin`
3. **Import Database**:
   - Click "Import" tab
   - Choose `backend/database/schema.sql`
   - Click "Go" to execute

This creates:
- Database: `ucsi_digital_library`
- Tables: `books`, `downloads`, `admin_users`, `admin_sessions`
- Default admin user: `admin@ucsi.edu.my` / `admin123`

### 2. File Permissions

Create and set permissions for upload directories:

```bash
# For Windows/XAMPP
mkdir backend/uploads/books/2024
mkdir backend/uploads/covers
mkdir backend/logs

# For Linux/Mac
chmod 755 backend/uploads/books/
chmod 755 backend/uploads/covers/
chmod 755 backend/logs/
```

### 3. PHP Configuration

In your `php.ini` file (XAMPP: `xampp/php/php.ini`):

```ini
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
max_input_time = 300
memory_limit = 256M

# Enable required extensions
extension=pdo_mysql
extension=gd
extension=fileinfo
```

Restart Apache after changes.

### 4. Backend Access

Place the backend folder in your XAMPP `htdocs` directory:

```
xampp/htdocs/digital-books-repository/backend/
```

API Base URL: `http://localhost/digital-books-repository/backend/api/`

## 🔌 API Endpoints

### Books Management

```http
GET    /api/books                    # List all books (with pagination)
GET    /api/books?search=query       # Search books
GET    /api/books?subject=Computer   # Filter by subject
GET    /api/books/{id}               # Get single book
POST   /api/books                    # Create new book
PUT    /api/books/{id}               # Update book
DELETE /api/books/{id}               # Soft delete book
DELETE /api/books/{id}?hard=true     # Hard delete book
```

### File Upload

```http
POST /api/upload                     # Upload book with PDF
```

**Form Data Parameters:**
- `upload_type`: `complete` | `pdf_only` | `cover_only`
- `pdf_file`: PDF file (required for complete/pdf_only)
- `cover_image`: Image file (optional)
- `title`: Book title (required)
- `author`: Author name (required)
- `subject`: Subject category (required)
- `description`: Book description (optional)
- `edition`: Edition (optional)
- `publication_date`: Publication date (optional)
- `publisher`: Publisher name (optional)
- `isbn`: ISBN number (optional)
- `source`: Source information (optional)

### File Download

```http
GET /api/download?id={book_id}       # Download PDF
GET /api/download?id={book_id}&email={email}  # Track with email
```

### Search

```http
GET /api/search?q={query}            # Full text search
GET /api/search?subject={subject}    # Subject search
GET /api/search?author={author}      # Author search
```

### Statistics

```http
GET /api/books?stats=true            # Book statistics
GET /api/books?subjects=true         # List all subjects
GET /api/books?recent=true           # Recent books
```

## 🧪 Testing the API

### 1. Test Database Connection

```bash
curl http://localhost/digital-books-repository/backend/api/books
```

Should return JSON with success response.

### 2. Test File Upload

```bash
curl -X POST \
  -F "upload_type=complete" \
  -F "title=Test Book" \
  -F "author=Test Author" \
  -F "subject=Computer Science" \
  -F "description=Test description" \
  -F "pdf_file=@/path/to/test.pdf" \
  http://localhost/digital-books-repository/backend/api/upload
```

### 3. Test Search

```bash
curl "http://localhost/digital-books-repository/backend/api/search?q=computer"
```

### 4. Test Download

```bash
curl "http://localhost/digital-books-repository/backend/api/download?id=1" \
  -o downloaded_book.pdf
```

## 🔧 Configuration

### Database Configuration

Edit `backend/api/config/database.php` for your environment:

```php
private $host = 'localhost';        # Database host
private $db_name = 'ucsi_digital_library';  # Database name
private $username = 'root';         # Database username
private $password = '';             # Database password (empty for XAMPP)
```

### CORS Configuration

Edit `backend/api/config/config.php` to allow your frontend:

```php
header('Access-Control-Allow-Origin: http://localhost:5173'); // Your React app URL
```

### File Limits

Adjust in `backend/api/config/config.php`:

```php
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_FILE_TYPES', ['application/pdf']);
```

## 📊 Features

### Security Features
- ✅ File type validation (PDF only)
- ✅ File size limits (50MB default)
- ✅ Secure filename generation
- ✅ SQL injection prevention (PDO)
- ✅ XSS protection
- ✅ CSRF protection headers
- ✅ Directory traversal prevention
- ✅ Rate limiting for downloads

### File Management
- ✅ Organized directory structure (year/month)
- ✅ PDF metadata extraction
- ✅ Cover image upload & resize
- ✅ File integrity checking (SHA256)
- ✅ Automatic cleanup on delete

### API Features
- ✅ RESTful endpoints
- ✅ JSON responses
- ✅ Pagination support
- ✅ Full-text search
- ✅ Advanced filtering
- ✅ Download tracking
- ✅ Activity logging
- ✅ Error handling

### Database Features
- ✅ Optimized indexes
- ✅ Full-text search indexes
- ✅ Foreign key constraints
- ✅ Soft delete support
- ✅ Timestamp tracking
- ✅ Download statistics

## 🚀 Deployment to Hostinger

### 1. Upload Files
- Upload entire `backend` folder to your domain's root
- Set correct file permissions (755 for directories, 644 for files)

### 2. Database Setup
- Create MySQL database via Hostinger control panel
- Import `backend/database/schema.sql`
- Update database credentials in `config/database.php`

### 3. Configuration
- Update CORS origins for your production domain
- Set proper file upload limits
- Configure error logging

### 4. SSL & Security
- Enable SSL certificate
- Update API URLs to use HTTPS
- Configure security headers

## 📝 Sample Data

The database schema includes sample books. To add your own:

1. **Via API**: Use the upload endpoint
2. **Direct Database**: Insert into `books` table
3. **Bulk Import**: Create custom script for bulk uploads

## 🐛 Troubleshooting

### Common Issues

1. **"Database connection failed"**
   - Check database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Verify database exists

2. **"Failed to move uploaded file"**
   - Check directory permissions
   - Ensure upload directories exist
   - Check PHP upload settings

3. **"Method not allowed"**
   - Verify Apache mod_rewrite is enabled
   - Check `.htaccess` file is being read

4. **CORS errors from frontend**
   - Update allowed origins in `config/config.php`
   - Check browser developer tools for specific error

### Log Files

Check these locations for error information:
- `backend/logs/api.log` - Application logs
- XAMPP logs in `xampp/apache/logs/error.log`
- PHP error logs (check `php.ini` for location)

## 🔄 Updating React Frontend

To connect your React frontend to this PHP backend:

1. **Update API base URL** in your React app
2. **Replace mock data** with API calls
3. **Add file upload components**
4. **Handle authentication** if needed
5. **Update download links** to use the download endpoint

Example API integration:
```javascript
const API_BASE = 'http://localhost/digital-books-repository/backend/api';

// Fetch books
const response = await fetch(`${API_BASE}/books`);
const data = await response.json();
```

---

## 📞 Support

For issues with this backend implementation:
1. Check the troubleshooting section above
2. Review server logs
3. Test individual endpoints with curl/Postman
4. Verify database connection and data

The backend is now fully functional and ready for testing with XAMPP!