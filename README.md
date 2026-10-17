# 🎌 HelloAni — Premium Anime Streaming Platform

A production-grade anime streaming SaaS platform built with PHP, MySQL, Tailwind CSS, and Vanilla JavaScript.

## ✨ Features

- **Premium Dark UI** — Glassmorphism, neon glows, smooth animations
- **Hero Slider** — Swiper.js featured anime banner
- **Anime Catalog** — Browse, search, filter by genre/type/status
- **Video Player** — Supports iframe, MP4, HLS, embed codes
- **Multi-Server** — Multiple streaming sources with auto-fallback
- **User System** — Register, login, profiles, watchlist, history
- **Admin Panel** — Full CMS with anime/episode/user management
- **Comment System** — User comments with moderation
- **AJAX Search** — Instant live search results
- **SEO Optimized** — Meta tags, clean URLs, schema markup
- **Responsive** — Mobile-first, works on all devices
- **Security** — CSRF protection, prepared statements, XSS prevention

## 🚀 Quick Start

### Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite OR PHP built-in server

### Installation

1. **Clone the project**
   ```bash
   cd helloani
   ```

2. **Create MySQL database**
   ```bash
   mysql -u root -p < database/schema.sql
   ```

3. **Configure database** — Edit `config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'helloani');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

4. **Create upload directories**
   ```bash
   mkdir -p uploads/posters uploads/banners uploads/avatars uploads/subtitles
   ```

5. **Start the server**
   ```bash
   php -S localhost:8000 router.php
   ```

6. **Open** http://localhost:8000

## 🔐 Demo Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@helloani.com | Admin@123 |
| User | demo@helloani.com | Admin@123 |

## 📁 Project Structure

```
/helloani
├── index.php          # Router
├── config.php         # Configuration
├── router.php         # Dev server router
├── .htaccess          # Apache rewrite
├── /admin             # Admin panel (8 pages)
├── /api               # AJAX API endpoints
├── /auth              # Authentication pages
├── /pages             # Frontend pages
├── /templates         # Header/footer templates
├── /includes          # Core PHP (db, auth, csrf, helpers)
├── /database          # SQL schema
├── /assets            # CSS, JS, images
└── /uploads           # User uploads
```

## 🎨 Tech Stack

- **Frontend:** HTML5, Tailwind CSS v3, Custom CSS, Vanilla JS
- **Backend:** Core PHP 8, PDO, MySQL
- **Libraries:** Swiper.js, AOS.js, Chart.js, HLS.js, Font Awesome
- **Security:** CSRF tokens, bcrypt hashing, prepared statements

## 🎬 Admin Panel

Access at `/admin` — Features:
- Dashboard with analytics charts
- Anime CRUD management
- Episode & server management (iframe/mp4/hls/embed)
- User management (ban/promote/delete)
- Comment moderation
- Site settings (SEO, SMTP, themes)
- Ad manager
- API key management
- Analytics with Chart.js

## 🌐 Deployment

### cPanel / Hostinger
1. Upload files to `public_html`
2. Import `database/schema.sql` via phpMyAdmin
3. Update `config.php` with database credentials
4. Ensure `.htaccess` is working

### Apache Config
Make sure `mod_rewrite` is enabled:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

## 📄 License

MIT License — Built with ❤️ for anime fans.
