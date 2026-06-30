<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateHtaccess extends Command
{
    protected $signature = 'make:htaccess';
    protected $description = 'Generate default Laravel .htaccess file in public directory';

    public function handle()
    {
        $path = public_path('.htaccess');

        $content = <<<HTACCESS
<IfModule mod_rewrite.c>
Options +FollowSymLinks
RewriteEngine On

RewriteCond %{REQUEST_URI} !^/public/

RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f

RewriteRule ^(.*)$ /public/$1 
#RewriteRule ^ index.php [L]
RewriteRule ^(/)?$ public/index.php [L] 
</IfModul
HTACCESS;

        File::put($path, $content);

        $this->info('.htaccess generated successfully at: ' . $path);
    }
}
