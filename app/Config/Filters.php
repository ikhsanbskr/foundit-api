<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    /**
     * Konfigurasi Aliases untuk Filter kustom kita.
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        
        // Panggil filter kustom buatan kita tanpa konflik namespace
        'cors'          => \App\Filters\Cors::class 
    ];

    /**
     * Filter khusus yang wajib dan diproses paling awal.
     */
    public array $required = [
        'before' => [
            // 'forcehttps', // MATIKAN INI SAAT DEV LOKAL AGAR REQUEST TIDAK TERPARS/REDIRECT
            'pagecache',  
        ],
        'after' => [
            'pagecache',   
            'performance', 
            'toolbar',     
        ],
    ];

    /**
     * Filter global yang berjalan di setiap request masuk.
     */
    public array $globals = [
        'before' => [
            'cors', // CORS harus berada di urutan paling atas global sebelum filter lainnya!
        ],
        'after' => [
            // 'honeypot',
        ],
    ];

    public array $methods = [];
    public array $filters = [];
}