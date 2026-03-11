#!/bin/bash

# Hata durumunda durdur
set -e

echo "🚀 Deploy başlatılıyor..."

# Scriptin bulunduğu dizini referans alarak proje köküne git (backend/scripts/.. -> backend -> .. -> root)
cd "$(dirname "$0")/../.."
echo "📂 Çalışma dizini: $(pwd)"

# 1. Kodları Güncelle
echo "📥 Git pull yapılıyor..."
git pull origin main

# 2. Backend Kurulumu
echo "🐘 Backend bağımlılıkları yükleniyor..."
cd backend
# PHP sürüm uyumsuzluğunu önlemek için update kullanıyoruz
composer update --no-dev --optimize-autoloader

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

# Backend .env dosyasından Reverb App Key'i al
REVERB_APP_KEY=$(grep REVERB_APP_KEY ../backend/.env | cut -d '=' -f2)

# Frontend için .env.production dosyası oluştur
echo "📝 Frontend .env.production dosyası oluşturuluyor..."
rm -f .env .env.production # Eski dosyaları temizle
cat > .env.production <<EOF
VITE_REVERB_APP_KEY=$REVERB_APP_KEY
VITE_REVERB_HOST="parayok.space"
VITE_REVERB_PORT="443"
VITE_REVERB_SCHEME="https"
EOF

# Build al
echo "🔨 Frontend build alınıyor..."
npm install && npm run build

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
