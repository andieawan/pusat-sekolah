<?php

use Illuminate\Support\Facades\Schedule;

// Cron di server cukup satu baris:  * * * * * cd /var/www/pusat && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('webhook:kirim')->everyMinute()->withoutOverlapping(10);
Schedule::command('pusat:bersihkan')->dailyAt('03:15');
