<?php

namespace App\Logging;

use DateTimeZone;
use Illuminate\Log\Logger;

/**
 * Scrive i timestamp dei log nel fuso configurato (default Europe/Rome) invece che in UTC.
 *
 * Perché non si tocca APP_TIMEZONE: quello è il fuso dell'intera applicazione — Carbon,
 * `now()`, e i timestamp letti/scritti a DB. Cambiarlo farebbe reinterpretare come ora locale
 * tutti i dati già salvati in UTC, spostandoli di 2 ore: rischio sproporzionato rispetto al
 * problema, che è solo leggere i log. Qui si sposta il solo orologio di Monolog.
 */
class SetLogTimezone
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->setTimezone(
            new DateTimeZone((string) config('logging.timezone'))
        );
    }
}
