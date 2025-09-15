# UCSI Digital Library - Complete API Guide

## 🎯 **System Overview**

This backend is specifically designed for **UCSI University Bangladesh Branch** with:
- **Public Access**: Anyone can browse and search books
- **Restricted Downloads**: Only verified UCSI students can download PDFs
- **OTP Verification**: Email-based verification system for students
- **Admin Management**: Complete admin panel for book management

---

## 🏗️ **Architecture**

### **Database Schema (Matches BookForm.tsx exactly)**

```sql
books table:
- id (auto-increment)
- title (required)
- author (required)
- subject (required)
- description (required)
- cover_image (optional - coverImage from form)
- pdf_url (optional - pdfUrl from form)
- edition (optional)
- publication_date (optional - publicationDate from form)
- publisher (optional)
- isbn (optional)
- book_hash (optional - bookHash from form)
- source (optional)
- status (active/inactive)
- created_at, updated_at

students table:
- student_id, email (@ucsiuniversity.edu.my)
- verification status

otp_verifications table:
- OTP codes for download verification
- Expires in 10 minutes
```

---

## 🌐 **API Endpoints**

### **📚 Book Management (Public + Admin)**

#### **GET /api/books** - List all books
```bash
# Basic listing
curl "http://localhost/digital-books-repository/backend/api/books"

# With pagination
curl "http://localhost/digital-books-repository/backend/api/books?page=1&limit=10"

# Search books
curl "http://localhost/digital-books-repository/backend/api/books?q=computer"

# Filter by subject
curl "http://localhost/digital-books-repository/backend/api/books?filter_subject=Computer Science"
```

#### **GET /api/books/{id}** - Get single book
```bash
curl "http://localhost/digital-books-repository/backend/api/books?id=1"
```

#### **POST /api/books** - Create new book (Admin only)
```bash
curl -X POST "http://localhost/digital-books-repository/backend/api/books" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Advanced Computer Science",
    "author": "Dr. John Smith",
    "subject": "Computer Science",
    "description": "Comprehensive guide to advanced CS concepts",
    "coverImage": "https://example.com/cover.jpg",
    "pdfUrl": "/uploads/books/advanced-cs.pdf",
    "edition": "3rd Edition",
    "publicationDate": "2024-01-15",
    "publisher": "Tech Publications",
    "isbn": "9781234567890",
    "source": "University Library"
  }'
```

#### **PUT /api/books/{id}** - Update book (Admin only)
```bash
curl -X PUT "http://localhost/digital-books-repository/backend/api/books?id=1" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Updated Book Title",
    "description": "Updated description"
  }'
```

#### **DELETE /api/books/{id}** - Delete book (Admin only)
```bash
# Soft delete
curl -X DELETE "http://localhost/digital-books-repository/backend/api/books?id=1"

# Hard delete (removes files)
curl -X DELETE "http://localhost/digital-books-repository/backend/api/books?id=1&hard=true"
```

---

### **🔍 Search & Discovery**

#### **GET /api/search** - Enhanced search
```bash
# Full text search
curl "http://localhost/digital-books-repository/backend/api/search?q=programming"

# Search by subject
curl "http://localhost/digital-books-repository/backend/api/search?subject=Mathematics"

# Search by author
curl "http://localhost/digital-books-repository/backend/api/search?author=John Smith"

# Combined search with pagination
curl "http://localhost/digital-books-repository/backend/api/search?q=computer&page=1&limit=5"
```

#### **GET /api/books?subjects=true** - Get all subjects
```bash
curl "http://localhost/digital-books-repository/backend/api/books?subjects=true"
```

#### **GET /api/books?stats=true** - Get statistics
```bash
curl "http://localhost/digital-books-repository/backend/api/books?stats=true"
```

---

### **🎓 UCSI Student Download System**

#### **Step 1: Request OTP for Download**
```bash
curl -X POST "http://localhost/digital-books-repository/backend/api/verify_download" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "request_otp",
    "email": "12345@ucsiuniversity.edu.my",
    "book_id": 1
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "OTP sent to 12345@ucsiuniversity.edu.my. The code will expire in 10 minutes.",
  "data": {
    "email": "12345@ucsiuniversity.edu.my",
    "book_id": 1,
    "book_title": "Introduction to Computer Science",
    "expires_in": 600
  }
}
```

#### **Step 2: Verify OTP and Get Download Token**
```bash
curl -X POST "http://localhost/digital-books-repository/backend/api/verify_download" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "verify_otp",
    "email": "12345@ucsiuniversity.edu.my",
    "otp_code": "123456",
    "book_id": 1
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "OTP verified successfully. Download authorized.",
  "data": {
    "download_token": "abc123def456...",
    "email": "12345@ucsiuniversity.edu.my",
    "book_id": 1,
    "expires_in": 300,
    "download_url": "/api/secure_download?token=abc123def456..."
  }
}
```

#### **Step 3: Download with Token**
```bash
curl "http://localhost/digital-books-repository/backend/api/secure_download?token=abc123def456..." \
  -o downloaded_book.pdf
```

---

### **📧 Email Verification System**

#### **Valid UCSI Email Format:**
- **Pattern**: `studentid@ucsiuniversity.edu.my`
- **Examples**:
  - `12345@ucsiuniversity.edu.my` ✅
  - `john123@ucsiuniversity.edu.my` ✅
  - `student@gmail.com` ❌
  - `test@ucsi.edu.my` ❌

#### **OTP Email Content:**
```
Subject: UCSI Digital Library - Download Verification Code

Dear Student (ID: 12345),

You have requested to download: "Introduction to Computer Science"

Your verification code is: 123456

Important:
- Code expires in 10 minutes
- Do not share this code
- Use it to complete your download

UCSI Digital Library - Bangladesh Branch
```

---

### **📁 File Upload (Admin Only)**

#### **POST /api/upload** - Upload book with PDF
```bash
curl -X POST "http://localhost/digital-books-repository/backend/api/upload" \
  -F "upload_type=complete" \
  -F "title=New Book Title" \
  -F "author=Author Name" \
  -F "subject=Computer Science" \
  -F "description=Book description here" \
  -F "edition=1st Edition" \
  -F "publisher=Publisher Name" \
  -F "isbn=9781234567890" \
  -F "pdf_file=@/path/to/book.pdf" \
  -F "cover_image=@/path/to/cover.jpg"
```

---

## 🛠️ **Frontend Integration Examples**

### **React API Service Functions**

```javascript
const API_BASE = 'http://localhost/digital-books-repository/backend/api';

// Get all books
export const fetchBooks = async (page = 1, search = '', subject = '') => {
  const params = new URLSearchParams({
    page: page.toString(),
    limit: '20',
    ...(search && { q: search }),
    ...(subject && { filter_subject: subject })
  });

  const response = await fetch(`${API_BASE}/books?${params}`);
  return response.json();
};

// Get single book
export const fetchBook = async (id) => {
  const response = await fetch(`${API_BASE}/books?id=${id}`);
  return response.json();
};

// Search books
export const searchBooks = async (query) => {
  const response = await fetch(`${API_BASE}/search?q=${encodeURIComponent(query)}`);
  return response.json();
};

// Request download OTP
export const requestDownloadOTP = async (email, bookId) => {
  const response = await fetch(`${API_BASE}/verify_download`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'request_otp',
      email,
      book_id: bookId
    })
  });
  return response.json();
};

// Verify OTP
export const verifyDownloadOTP = async (email, otpCode, bookId) => {
  const response = await fetch(`${API_BASE}/verify_download`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'verify_otp',
      email,
      otp_code: otpCode,
      book_id: bookId
    })
  });
  return response.json();
};

// Download with token
export const downloadBook = (downloadToken) => {
  window.open(`${API_BASE}/secure_download?token=${downloadToken}`, '_blank');
};
```

### **React Download Component Example**

```jsx
import React, { useState } from 'react';
import { requestDownloadOTP, verifyDownloadOTP, downloadBook } from './api';

const BookDownload = ({ book }) => {
  const [email, setEmail] = useState('');
  const [otp, setOtp] = useState('');
  const [step, setStep] = useState('email'); // 'email', 'otp', 'download'
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleRequestOTP = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const result = await requestDownloadOTP(email, book.id);
      if (result.success) {
        setStep('otp');
      } else {
        setError(result.message);
      }
    } catch (err) {
      setError('Failed to send OTP. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyOTP = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const result = await verifyDownloadOTP(email, otp, book.id);
      if (result.success) {
        downloadBook(result.data.download_token);
        setStep('download');
      } else {
        setError(result.message);
      }
    } catch (err) {
      setError('Failed to verify OTP. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="download-form">
      {step === 'email' && (
        <form onSubmit={handleRequestOTP}>
          <h3>Download "{book.title}"</h3>
          <p>Enter your UCSI University email to receive a verification code:</p>
          <input
            type="email"
            placeholder="studentid@ucsiuniversity.edu.my"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            pattern=".*@ucsiuniversity\.edu\.my$"
            required
          />
          <button type="submit" disabled={loading}>
            {loading ? 'Sending...' : 'Send OTP'}
          </button>
          {error && <p className="error">{error}</p>}
        </form>
      )}

      {step === 'otp' && (
        <form onSubmit={handleVerifyOTP}>
          <h3>Enter Verification Code</h3>
          <p>Check your email ({email}) for the 6-digit code:</p>
          <input
            type="text"
            placeholder="123456"
            value={otp}
            onChange={(e) => setOtp(e.target.value)}
            maxLength="6"
            pattern="[0-9]{6}"
            required
          />
          <button type="submit" disabled={loading}>
            {loading ? 'Verifying...' : 'Verify & Download'}
          </button>
          <button type="button" onClick={() => setStep('email')}>
            Back
          </button>
          {error && <p className="error">{error}</p>}
        </form>
      )}

      {step === 'download' && (
        <div>
          <h3>Download Started!</h3>
          <p>Your download should begin automatically. If not, check your browser's download folder.</p>
          <button onClick={() => setStep('email')}>
            Download Another Book
          </button>
        </div>
      )}
    </div>
  );
};
```

---

## 🚀 **XAMPP Setup & Testing**

### **1. Database Setup**
```bash
# Import the updated schema
mysql -u root -p < backend/database/updated_schema.sql
```

### **2. Test the API**
```bash
# Test basic connection
curl "http://localhost/digital-books-repository/backend/api/books"

# Test UCSI email validation
curl -X POST "http://localhost/digital-books-repository/backend/api/verify_download" \
  -H "Content-Type: application/json" \
  -d '{"action": "request_otp", "email": "12345@ucsiuniversity.edu.my", "book_id": 1}'
```

### **3. Check Logs**
```bash
# View API logs
tail -f backend/logs/api.log
```

---

## 🔒 **Security Features**

1. **Email Validation**: Only `@ucsiuniversity.edu.my` emails allowed
2. **OTP Expiry**: Codes expire in 10 minutes
3. **Rate Limiting**: 2-minute cooldown between OTP requests
4. **Token-based Downloads**: Secure tokens expire in 5 minutes
5. **Download Tracking**: All downloads logged with student info
6. **File Security**: Uploaded files stored outside web root
7. **Input Sanitization**: All inputs sanitized and validated

---

## 📊 **Admin Features**

- **Book Management**: Full CRUD operations matching BookForm.tsx
- **Student Analytics**: Track OTP requests and downloads
- **Download Reports**: View download statistics by book/student
- **File Management**: Upload PDFs and cover images
- **Data Validation**: Server-side validation matching frontend requirements

---

## 🌐 **Production Deployment (Hostinger)**

1. **Upload Files**: Upload backend folder to domain root
2. **Database**: Import `updated_schema.sql` via phpMyAdmin
3. **Configure**: Update database credentials in `config/database.php`
4. **CORS**: Update allowed origins for your production domain
5. **Email**: Configure SMTP for OTP email delivery
6. **SSL**: Enable SSL certificate for secure downloads

---

## 🧪 **Testing Checklist**

- [ ] Browse books without authentication
- [ ] Search functionality works
- [ ] UCSI email validation (reject non-UCSI emails)
- [ ] OTP generation and email delivery
- [ ] OTP verification and download token generation
- [ ] Secure download with valid token
- [ ] Token expiry handling
- [ ] Admin book management (CRUD operations)
- [ ] File upload functionality
- [ ] Download tracking and analytics

---

Your backend is now **perfectly aligned** with your BookForm.tsx and implements the complete UCSI student verification system! 🎉