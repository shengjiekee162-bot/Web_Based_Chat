# Production / cPanel checklist

Use this before going live.

## Config (cPanel)
- [ ] Create MySQL database + user in **cPanel → MySQL® Databases**, then **Add User To Database** (ALL PRIVILEGES)
- [ ] Import `database/database.sql` in **phpMyAdmin**
- [ ] On the server only: copy `includes/config.cpanel.example.php` → `includes/config.local.php`
- [ ] Set `DB_HOST` to `localhost` (not your home PC IP)
- [ ] Set `DB_NAME` / `DB_USER` / `DB_PASS` from cPanel
- [ ] Set `APP_URL` to your real HTTPS URL (e.g. `https://yourdomain.com`) — **not** `http://127.0.0.1:8000`
- [ ] Set `define('SHOW_DEMO_LOGIN', false);`
- [ ] Ensure `uploads/` and `logs/` are writable (chmod 755 or 775)
- [ ] Change admin password at `/admin/password.php`
- [ ] Remove or change demo users (`alice` / `bob` / `carol`)

**Do not** put cPanel DB credentials into local `config.php` and open `127.0.0.1:8000` — home PHP cannot reach cPanel MySQL unless Remote MySQL is enabled.

## Server
- [ ] PHP 8.3+ with `pdo_mysql`, `mbstring`, `fileinfo`, `json`
- [ ] `uploads/` and `logs/` writable
- [ ] HTTPS enabled (required for camera/mic off localhost)
- [ ] `uploads/.htaccess` denies direct browsing

## WebRTC
- [ ] Test voice/video on two real devices
- [ ] Add TURN servers if peers cannot connect
- [ ] Confirm browser permissions prompts appear

## Security
- [ ] CSRF works on forms/APIs
- [ ] File uploads reject bad types
- [ ] Admin uses separate session cookie
- [ ] App errors do not expose SQL traces to users

## Smoke test
- [ ] Register / login / logout
- [ ] Friend request + private chat
- [ ] Image / file / voice message
- [ ] Group create + message
- [ ] Forum post + report
- [ ] Admin disable user + review report
