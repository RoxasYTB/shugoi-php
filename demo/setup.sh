#!/bin/bash
# Shugoi PHP — Laravel demo setup
# Run: bash setup.sh

set -e

echo "=== Shugoi Laravel Demo ==="

# 1. Create Laravel project
echo "Creating Laravel project..."
composer create-project laravel/laravel shugoi-demo "^11.0"
cd shugoi-demo

# 2. Add Shugoi package
echo "Adding Shugoi package..."
composer require shugoi/shugoi-php

# 3. Publish config
echo "Publishing config..."
php artisan vendor:publish --tag=shugoi-config

# 4. Register middleware
echo "Registering middleware..."
cat > bootstrap/app.php << 'EOF'
<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\Shugoi\Laravel\ShugoiMiddleware::class);
        $middleware->excludeFrom(\Shugoi\Laravel\ShugoiMiddleware::class, ['__shugoi/*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
EOF

echo ""
echo "=== Done ==="
echo ""
echo "Next steps:"
echo "1. Edit .env and set:"
echo "   SHUGOI_SITE_KEY=sg_sk_live_YOUR_KEY"
echo "   SHUGOI_SECRET=your_secret"
echo "   SHUGOI_INTERNAL_URL=http://127.0.0.1:8080"
echo ""
echo "2. Run: php artisan serve"
echo "   Test: curl http://localhost:8000 (should be blocked)"
echo ""
