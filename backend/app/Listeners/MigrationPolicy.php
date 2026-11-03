<?php

namespace App\Listeners;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * What may be migrated, where, and what has to be saved first.
 *
 * Hooked onto CommandStarting rather than shadowing the migrate commands. The
 * previous approach redeclared `migrate` with a hand-copied list of options and
 * then reached into MigrateCommand to run the real thing — that copy had
 * already drifted from the framework (`--graceful` and `--schema-path` were
 * rejected outright, `--isolated` no longer exists), and the bypass was an
 * environment variable anyone could set. Listening leaves the commands alone:
 * new options keep working on their own.
 *
 * The policy:
 *
 *   development (local, development, testing)
 *       nothing blocked, nothing warned. A backup is offered, never imposed,
 *       and only when someone is there to answer.
 *
 *   integration, staging
 *       full backup first, always. migrate:fresh warns but proceeds.
 *
 *   production
 *       full backup first, always. migrate:fresh refused outright.
 *
 * The refusal here is the second line, not the first: the production database
 * user should not hold DROP in the first place.
 */
class MigrationPolicy
{
    /** Commands that change the schema and therefore fall under this policy. */
    private const MIGRATION_COMMANDS = [
        'migrate',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
    ];

    /** The destructive ones: they drop before they build. */
    private const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
    ];

    private const DEVELOPMENT_ENVIRONMENTS = ['local', 'development', 'testing'];

    /** Guards against re-entering while the backup command runs. */
    private static bool $running = false;

    public function handle(CommandStarting $event): void
    {
        $command = (string) $event->command;

        if (self::$running || ! in_array($command, self::MIGRATION_COMMANDS, true)) {
            return;
        }

        // --pretend prints SQL and touches nothing: no backup, no ceremony.
        if ($event->input->hasParameterOption('--pretend')) {
            return;
        }

        $environment = app()->environment();
        $destructive = in_array($command, self::DESTRUCTIVE_COMMANDS, true);
        $output = $event->output;

        if (in_array($environment, self::DEVELOPMENT_ENVIRONMENTS, true)) {
            $this->offerBackup($event, $command);

            return;
        }

        if ($destructive && $environment === 'production') {
            throw new RuntimeException(
                "[{$command}] è rifiutato in produzione: ricostruisce il database da zero. "
                .'Se serve davvero, va fatto a mano da chi può risponderne.'
            );
        }

        $output?->writeln(
            $destructive
                ? "  <fg=black;bg=yellow> ATTENZIONE </> [{$command}] ricostruisce il database da zero in ambiente [{$environment}]."
                : "  <fg=black;bg=yellow> ATTENZIONE </> [{$command}] modifica lo schema in ambiente [{$environment}]."
        );

        $this->backup($output, obbligatorio: true);
    }

    /**
     * Outside development the backup is not a question.
     */
    private function backup(?object $output, bool $obbligatorio): void
    {
        $output?->writeln('  Backup del database in corso...');

        self::$running = true;

        try {
            $exit = Artisan::call('db:backup');
        } finally {
            self::$running = false;
        }

        if ($exit === 0) {
            $output?->writeln('  <info>Backup completato.</info>');

            return;
        }

        if ($obbligatorio) {
            throw new RuntimeException(
                'Backup fallito: la migrazione si ferma qui. '
                .'Fuori dallo sviluppo non si tocca lo schema senza una copia.'
            );
        }

        $output?->writeln('  <comment>Backup fallito — si prosegue comunque (ambiente di sviluppo).</comment>');
    }

    /**
     * In development the backup is offered, and only when someone can answer:
     * an unattended run must never hang waiting for a prompt.
     */
    private function offerBackup(CommandStarting $event, string $command): void
    {
        $output = $event->output;

        if ($event->input->hasParameterOption(['--no-interaction', '-n'])
            || $event->input->hasParameterOption('--force')
            || ! $event->input->isInteractive()) {
            return;
        }

        $output?->writeln("  [{$command}] sta per modificare lo schema. Verrà chiesto se fare un backup.");

        $question = new ConfirmationQuestion(
            '  Vuoi un backup del database prima di procedere? (s/N) ',
            false
        );

        $helper = new QuestionHelper;

        if ($helper->ask($event->input, $output, $question)) {
            $this->backup($output, obbligatorio: false);
        }
    }
}
