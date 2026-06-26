# Bookmarks System

A PHP + MariaDB bookmark manager with user registration, admin approval, folders, tags, sharing, and public bookmarks.

## Features

- User registration with admin approval
- Private and public bookmarks
- Folders and tags for organization
- Share bookmarks with other users
- Public bookmark pages
- Admin panel for user and content moderation
- Password change functionality
- CSRF protection and secure authentication

## Requirements

- PHP 7.4+
- MariaDB/MySQL 5.7+
- Web server (Apache/Nginx)

## Installation

### 1. Database Setup

Create the database and tables:

```bash
mysql -u root -p < schema.sql
```

### 2. Configure Database Credentials

Create `/etc/bookmarks.ini`:

```ini
[database]
host = localhost
dbname = bookmarks_db
username = your_db_user
password = your_db_password
```

Set proper permissions:

```bash
chmod 640 /etc/bookmarks.ini
chown www-data:www-data /etc/bookmarks.ini
```

### 3. First Admin Account

After installation, create your first admin account:

```bash
php -r "
require 'config/database.php';
\$hash = password_hash('your_password', PASSWORD_BCRYPT);
\$pdo->prepare('INSERT INTO users (username, email, password_hash, approved, is_admin) VALUES (?, ?, ?, 1, 1)')->execute(['admin', 'your@email.com', \$hash]);
echo 'Admin created.\n';
"
```

Or use the default admin account:
- Username: `admin`
- Password: `admin123`

**Change the default password immediately after first login.**

## Usage

### URLs

- `/bookmarks/register.php` - User registration
- `/bookmarks/login.php` - Login
- `/bookmarks/index.php` - Dashboard
- `/bookmarks/settings.php` - Change password
- `/bookmarks/admin.php` - Admin panel (admin only)
- `/bookmarks/public/bookmark.php?id=X` - Public bookmark
- `/bookmarks/public/user.php?username=X` - User's public bookmarks

### Admin Panel

The admin panel allows you to:
- Approve/reject user registrations
- Manage users (make admin, disable, delete)
- View and delete public bookmarks
- View system statistics

### Adding Bookmarks

1. Click "Add Bookmark" on the dashboard
2. Enter URL, title, and optional description
3. Select folder and add tags (comma-separated)
4. Set visibility (private or public)

### Organizing

- **Folders**: Click the + button in the Folders sidebar
- **Tags**: Add comma-separated tags when creating/editing bookmarks
- **Filter**: Use the sidebar to filter by folder, tag, or visibility

### Sharing

1. Click the three-dot menu on a bookmark
2. Select "Share"
3. Enter the username to share with
4. The bookmark will appear in their "Shared with Me" section

## Security

- Passwords hashed with bcrypt
- CSRF tokens on all forms
- XSS protection with htmlspecialchars
- SQL injection prevention with prepared statements
- Admin-only access to admin panel
- User registration requires admin approval

## File Structure

```
bookmarks/
├── admin.php              # Admin panel
├── api/                   # AJAX endpoints
│   ├── bookmarks.php      # Bookmark CRUD
│   ├── folders.php        # Folder CRUD
│   └── share.php          # Sharing functionality
├── config/
│   └── database.php       # Database connection
├── css/
│   └── style.css          # Custom styles
├── includes/
│   ├── auth.php           # Authentication functions
│   ├── footer.php         # Common footer
│   ├── functions.php      # Shared functions
│   └── header.php         # Common header
├── index.php              # Dashboard
├── js/
│   └── app.js             # Client-side JavaScript
├── login.php              # Login page
├── logout.php             # Logout handler
├── public/
│   ├── bookmark.php       # Public bookmark view
│   └── user.php           # User's public page
├── register.php           # Registration page
├── schema.sql             # Database schema
├── settings.php           # User settings
└── .htaccess              # Security rules
```

## Troubleshooting

- **Database connection error**: Check `/etc/bookmarks.ini` credentials
- **Permission denied**: Ensure web server user can read `/etc/bookmarks.ini`
- **403 Forbidden**: Check `.htaccess` and file permissions
- **Session issues**: Verify session directory is writable
