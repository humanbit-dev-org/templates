# Laravel: Gestione di Cron, Scheduler e Jobs

Questo documento descrive come organizzare i comandi Artisan per attività cron, come utilizzare Jobs per l'elaborazione dei file, e come impostare il cron di sistema per far girare lo scheduler di Laravel.

---

## 1. Struttura consigliata dei file

```
app/
 ├─ Console/
 │   ├─ Commands/
 │   │   └─ Cron/                 <-- Comandi destinati ad attività cron
 │   │       └─ CheckTargetingFiles.php
 └─ Jobs/
     └─ ProcessTargetingFile.php  <-- Job che processa i file
```

- **Commands/Cron/**: contiene tutti i comandi Artisan usati per attività cron, ma che possono essere eseguiti manualmente.
- **Jobs/**: contiene Jobs che eseguono l'elaborazione effettiva dei file, gestiti da Laravel Queue.

---

## 2. Creare un comando Artisan per il Cron

Esempio: `CheckTargetingFiles` dentro `app/Console/Commands/Cron/CheckTargetingFiles.php`

```php
<?php

namespace App\Console\Commands\Cron;

use Illuminate\Console\Command;
use App\Jobs\ProcessTargetingFile;

class CheckTargetingFiles extends Command
{
    protected $signature = 'targeting:check';
    protected $description = 'Check incoming folder and dispatch job if file exists';

    public function handle()
    {
        $folder = storage_path('incoming');
        $files = glob($folder . '/*.csv');

        if (empty($files)) {
            $this->info('No files to process.');
            return 0;
        }

        foreach ($files as $file) {
            // Dispatch su queue 'targeting'
            ProcessTargetingFile::dispatch($file)->onQueue('targeting');
            $this->info("Dispatched job for file: $file");
        }

        return 0;
    }
}
```

- Questo comando controlla una cartella di input (`storage/incoming`) e invia un Job per ogni file trovato.
- Il Job `ProcessTargetingFile` si occupa di elaborare i file, separando la logica dal comando cron.

---

## 3. Job per l'elaborazione dei file

Esempio: `ProcessTargetingFile` dentro `app/Jobs/ProcessTargetingFile.php`

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\TargetingImportCsvService;

class ProcessTargetingFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function handle()
    {
        TargetingImportCsvService::importTargetingCsv($this->filePath);
    }
}
```

- I Job vengono messi su una queue specifica (`targeting`) e processati dal worker dedicato.

---

## 4. Scheduler di Laravel

In `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('targeting:check')
    ->everyMinute()
    ->withoutOverlapping();
```

- `withoutOverlapping()` impedisce più esecuzioni contemporanee.
- Lo scheduler chiama il comando Artisan, che controlla la cartella e dispatcha i Job.

---

## 5. Impostazione del cron di sistema

Esempio per Linux / server minimo:

```bash
* * * * * /usr/local/bin/php /var/www/html/codebase/artisan schedule:run >> /var/www/html/codebase/storage/logs/scheduler.log 2>&1
```

- Questo cron esterno serve **solo a eseguire lo scheduler di Laravel**, non contiene logica.
- Lo scheduler decide quali comandi eseguire e quando.
- Tutti i comandi Artisan rimangono in `app/Console/Commands/Cron` e possono essere eseguiti manualmente.

---

## 6. Worker della Queue

Assicurati di far partire un worker per la queue `targeting`:

Crea un file `supervisord.conf`

[supervisord]
nodaemon=true

[program:apache]
command=/usr/sbin/apache2ctl -D FOREGROUND
autorestart=true

[program:laravel-scheduler]
command=/usr/local/bin/php /var/www/html/codebase/artisan schedule:work
directory=/var/www/html/codebase
autorestart=true

[program:laravel-worker]
command=/usr/local/bin/php /var/www/html/codebase/artisan queue:work --queue=targeting --sleep=3 --tries=3
directory=/var/www/html/codebase
autorestart=true

---

### ✅ Vantaggi di questa organizzazione

- Separazione chiara tra **comandi cron** e **logica di elaborazione dei file**.
- Tutti i comandi sono versionati nel progetto e possono essere eseguiti manualmente.
- Scheduler di Laravel gestisce il lock e la pianificazione, evitando processi paralleli.
- Cron esterno serve solo come trigger per Laravel Scheduler.

