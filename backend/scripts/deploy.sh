#!/bin/bash

# Hata durumunda durdur
set -e

echo "🚀 Deploy başlatılıyor..."

# Proje ana dizinine git
cd /var/www/html/parayok

# 1. Kodları Güncelle
echo "📥 Git pull yapılıyor..."
git pull origin main

# 2. Backend Kurulumu
echo "🐘 Backend bağımlılıkları yükleniyor..."
cd backend
composer install --no-dev --optimize-autoloader

echo "🗄️ Veritabanı migrate ediliyor..."
php artisan migrate --force

echo "🧹 Cache temizleniyor..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 3. Frontend Build ve Taşıma
echo "🎨 Frontend build ediliyor..."
cd ../frontend
npm install
npm run build

echo "🚚 Build dosyaları backend'e taşınıyor..."
# Backend public temizliği (eski build dosyaları)
rm -rf ../backend/public/assets
rm -f ../backend/public/index.html

# Yeni dosyaları kopyala
cp -r dist/assets ../backend/public/
cp dist/index.html ../backend/public/

# Favicon vb. diğer statik dosyalar varsa onları da kopyala (opsiyonel)
# cp dist/favicon.ico ../backend/public/

echo "✅ Deploy başarıyla tamamlandı! (https://parayok.space)"
