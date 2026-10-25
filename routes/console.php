<?php

use Illuminate\Support\Facades\Schedule;

// Pengingat & tunggakan LPJ setiap hari kerja pagi (Perbup Pasal 19)
Schedule::command('ehibah:lpj-reminders')->dailyAt('06:30')->withoutOverlapping();

// Backup terjadwal: database setiap malam, penuh (database + berkas) setiap Minggu
Schedule::command('ehibah:backup DB --verify')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('ehibah:backup FULL --verify')->weeklyOn(0, '02:00')->withoutOverlapping();

// Keutuhan audit trail diperiksa harian
Schedule::command('audit:verify')->dailyAt('03:30');
