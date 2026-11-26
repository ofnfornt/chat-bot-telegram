FROM php:8.3-cli

# تنظیم پوشه کاری
WORKDIR /app

# کپی کردن تمام فایل‌های پروژه به داخل کانتینر
COPY . .

# ساخت پوشه داده‌ها (برای ذخیره‌سازی JSON)
RUN mkdir -p /app/data && chmod 777 /app/data

# تنظیم متغیر محیطی برای پورت (Render خودش این را ست می‌کند)
ENV PORT=10000

# اجرای اسکریپت PHP
CMD ["php", "-S", "0.0.0.0:10000", "index.php"]
