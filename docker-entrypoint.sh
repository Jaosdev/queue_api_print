#!/bin/bash

echo "⏳ Esperando que RabbitMQ esté disponible..."
for i in {1..10}; do
  nc -z rabbitmq 5672 && break
  sleep 1
done

echo "✅ RabbitMQ está disponible. Ejecutando setup.php..."
php /var/www/html/rabbitmq/setup.php

echo "📁 Verificando carpeta /var/www/html/queue..."
mkdir -p /var/www/html/queue
chown -R www-data:www-data /var/www/html/queue
echo "🔧 Reparando permisos de /var/www/html/queue"
chmod -R 777 /var/www/html/queue
echo "✅ Carpeta lista con permisos correctos."

echo "🚀 Iniciando supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
