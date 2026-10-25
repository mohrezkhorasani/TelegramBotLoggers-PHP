# Telegram Bot Logger (PHP)

سیستم توزیع بار و ارسال لاگ تلگرام با چندین ربات به صورت **Round-Robin** و مکانیزم خودکار **Fallback / Retry** نوشته شده با **PHP 8.1+**.

---

## 🔗 پروژه‌های مرتبط (Related Repositories)

- 🔷 **نسخه دات‌نت (.NET Core):** [TelegramBotLoggers-DotNet](https://github.com/mohrezkhorasani/TelegramBotLoggers-DotNet)
- 📌 **ریپازیتوری اولیه پروژه:** [TelegramBotLoggers](https://github.com/mohrezkhorasani/TelegramBotLoggers)

---

## 🌟 ویژگی‌ها (Key Features)

- 🔄 **توزیع بار چرخشی (Round-Robin Load Balancing):** درخواست‌ها به ترتیب بین ۱۱ ربات تلگرام تقسیم می‌شوند تا از لیمیت و خطای 429 تلگرام جلوگیری شود.
- 🔁 **خطایابی و فال‌بک زنجیره‌ای (Cascading Failover):** در صورت بروز هرگونه خطا (قطعی اینترنت، فیلترینگ، خطای تلگرام یا مسدود شدن بات)، سیستم بلافاصله بات بعدی را امتحان می‌کند تا در یک دور کامل تمام بات‌ها تست شوند.
- 🔒 **حفاظت کامل از توکن‌ها (Security First):** توکن‌های ربات‌ها فقط در فایل محلی `.env` نگهداری می‌شوند و این فایل در `.gitignore` نادیده گرفته شده است تا هیچ اطلاعات حساسی روی گیت‌هاب پابلیک نرود.
- ⚙️ **همزمانی امن (Atomic Concurrency):** مدیریت پوینتر Round-Robin با قفل فایلی انحصاری (`flock`) انجام می‌شود تا در محیط‌های چندپروسسی (مانند PHP-FPM، Apache و CLI) هیچ Race Condition رخ ندهد.
- 🎯 **پشتیبانی کامل از ویژگی‌های تلگرام:** پشتیبانی از تاپیک سوپرگروه‌ها (`topic_id` / `message_thread_id`)، ریپلای به پیام (`message_id` / `reply_parameters`)، و فرمت HTML.
- 🚀 **بدون نیاز به کتابخانه خارجی (Zero-Dependency):** اجرای سبک و سریع بدون نیاز اجباری به کامپوزر یا سرویس‌های جانبی (مانند ردیس یا دیتابیس).
- 🐳 **آماده برای داکر (Docker & Docker Compose):** استقرار آسان در هر سرور لینوکس یا ویندوز تنها با یک دستور.

---

## 🏗️ ساختار پروژه (Directory Structure)

```text
php/
├── .env                  # فایل حاوی توکن‌های واقعی (در گیت نادیده گرفته می‌شود)
├── .env.example          # قالب نمونه بدون توکن‌های حساس (جهت کامیت در گیت)
├── .gitignore            # نادیده گرفتن فایل‌های حساس و لاگ‌ها
├── composer.json         # تعریف ساختار PSR-4
├── Dockerfile            # ایمیج داکر مبتنی بر Alpine
├── docker-compose.yml    # اجرای سریع در داکر
├── index.php             # هدایتگر مسیرهای ریشه
├── public/
│   └── index.php         # کنترلر ورودی سرور وب
├── src/
│   ├── autoload.php      # لودر خودکار بدون نیاز به composer install
│   ├── Env.php           # پارسر و لودر فایل .env
│   ├── BotPool.php       # مدیریت لیست توکن‌های ربات‌ها
│   ├── RoundRobinState.php # مدیریت اتمیک پوینتر ربات فعال با flock
│   ├── TelegramClient.php  # ارسال پیام به تلگرام با cURL
│   └── LoggerController.php# کنترلر پردازش لاگ و حلقه Failover
├── storage/              # محل ذخیره ایندکس چرخش و وضعیت‌ها
└── tests/
    └── test_round_robin.php # تست خودکار اعتبارسنجی، چرخش و فال‌بک
```

---

## 🚀 راهنمای نصب و اجرا (Quick Start)

### ۱. تنظیم متغیرهای محیطی (`.env`)
فایل `.env.example` را به `.env` کپی کنید:

```bash
cp .env.example .env
```

سپس در فایل `.env`، توکن‌های ربات‌های خود را با کاما جدا کرده و ذخیره نمایید:
```env
TELEGRAM_BOTS="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ,987654321:ZYXwvuTsRqPoNmlKjIhGfEdCba"
DEFAULT_CHAT_ID="-1001234567890"
DEFAULT_TOPIC_ID="1"
APP_PORT=8080
TELEGRAM_TIMEOUT=10
```

---

### ۲. اجرای پروژه (روش‌های مختلف)

#### روش ۱: سرور داخلی PHP (توسعه محلی):
```bash
php -S 0.0.0.0:8080 -t public
```

یا مستقیماً:
```bash
php -S 0.0.0.0:8080
```

#### روش ۲: با Docker Compose:
```bash
docker compose up -d
```

#### روش ۳: با Nginx و PHP-FPM:
تنظیم `root` در کانفیگ Nginx به دایرکتوری `public/` پروژه:
```nginx
server {
    listen 80;
    server_name logger.yourdomain.com;
    root /path/to/php/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## 📡 مستندات API (API Documentation)

### ۱. بررسی سلامت و وضعیت سیستم
- **متد:** `GET /` یا `GET /health`

**نمونه پاسخ:**
```json
{
  "status": "online",
  "service": "Telegram Bot Logger API (PHP)",
  "configuredBots": 11,
  "nextBotIndex": 0,
  "timestamp": "2026-10-21T12:00:00+03:30"
}
```

---

### ۲. ارسال لاگ یا پیام
- **متد:** `POST /api/telegram/send`
- **Content-Type:** `application/json`

#### نمونه بدنه درخواست (Request Body):
```json
{
  "chat_id": -1003186245897,
  "message": "<b>[CRITICAL]</b> پایگاه داده پاسخگو نیست!\n<code>Connection timed out at 12:45</code>",
  "topic_id": 59,
  "message_id": 0
}
```

#### پارامترهای درخواست:
| پارامتر | نوع | الزامی | توضیحات |
| :--- | :--- | :--- | :--- |
| `chat_id` | `int` یا `string` | بله | شناسه چت، کانال، یا سوپرگروه تلگرام |
| `message` | `string` | بله | متن پیام یا لاگ (قالب‌بندی HTML مجاز است) |
| `topic_id` | `int` | خیر | شناسه تاپیک / فروم در سوپرگروه (Thread ID) |
| `message_id` | `int` | خیر | شناسه پیام جهت ریپلای کردن (Reply Parameters) |

---

### ۳. نمونه پاسخ‌ها (Responses)

#### پاسخ موفق (HTTP 200 OK):
```json
{
  "success": true,
  "usedBotIndex": 3,
  "attemptCount": 1,
  "messageId": 45892,
  "message": "sent with 3th"
}
```

#### پاسخ در صورت وقوع خطا در تمام بات‌ها (HTTP 502 Bad Gateway):
اگر تمام ۱۱ بات در طول حلقه با خطا مواجه شوند:
```json
{
  "success": false,
  "error": "All configured Telegram bots failed to send the message after a full round-robin retry cycle.",
  "totalBotsTried": 11,
  "attempts": [
    { "botIndex": 0, "attempt": 1, "error": "Telegram API Error [400]: Bad Request: chat not found" },
    { "botIndex": 1, "attempt": 2, "error": "Telegram API Error [400]: Bad Request: chat not found" }
  ]
}
```

---

## 🧪 اجرای تست‌ها (Running Tests)

پروژه دارای یک اسکریپت تست خودکار است که لود متغیرها، پوینتر چرخشی، اعتبارسنجی ورودی‌ها و مکانیزم فال‌بک را شبیه‌سازی و بررسی می‌کند:

```bash
php tests/test_round_robin.php
```

خروجی تست‌ها:
```text
=== Telegram Bot Logger Test Suite ===

[TEST 1] Loading BotPool...
Found 11 configured bots.
✅ PASSED: Exactly 11 bot tokens successfully loaded from .env.

[TEST 2] Testing Round-Robin Pointer Advancement...
✅ PASSED: Round-robin sequence matched: 0 -> 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7 -> 8 -> 9 -> 10 -> 0 -> 1

[TEST 3] Testing Input Validation...
✅ PASSED: Correctly rejected missing chat_id.
✅ PASSED: Correctly rejected missing message.

[TEST 4] Testing Fallback Retry Mechanism...
✅ PASSED: Fallback cascade succeeded on attempt 4 using bot #3.

[TEST 5] Testing Full Cycle Exhaustion (All Bots Fail)...
✅ PASSED: Completed full 11-bot loop, correctly caught all errors and returned HTTP 502.

🎉 ALL TESTS COMPLETED SUCCESSFULLY!
```

---

## 🔒 نکات امنیتی برای انتشار عمومی (Security Notes)

- فایل `.env` که شامل توکن‌های واقعی ربات‌هاست در فایل `.gitignore` قرار دارد و **هرگز نباید در گیت کامیت یا پوش شود**.
- تنها فایل `.env.example` را به عنوان نمونه کامیت کنید.
- پوشه `storage/` نیز از کامیت فایل‌های وضعیت و لاگ‌ها محافظت شده است.
- در صورت تغییر توکن‌ها یا اضافه کردن بات جدید، کافیست مقدار `TELEGRAM_BOTS` در `.env` به‌روزرسانی شود؛ سیستم به صورت داینامیک تعداد ربات‌ها را شناسایی و توزیع می‌کند.
