<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Diagrams\MermaidErdEmitter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final class DiagramsErd extends Command
{
    private const MARKER_START = '<!-- erd:start -->';

    private const MARKER_END = '<!-- erd:end -->';

    protected $signature = 'diagrams:erd {--check : Fail if committed ERD files differ from the schema}';

    protected $description = 'Regenerate the Mermaid ERD files in docs/diagrams/erd from the live database schema';

    public function handle(): int
    {
        /** @var array<string, array{title: string, tables: array<int, string>}> $groups */
        $groups = config('diagrams.groups');
        /** @var array<int, string> $excluded */
        $excluded = config('diagrams.exclude');
        /** @var array<string, array{name: string, targets: array<int, string>}> $morphs */
        $morphs = config('diagrams.morphs');
        /** @var string $outputPath */
        $outputPath = config('diagrams.output_path');

        $emitter = new MermaidErdEmitter($groups, $this->fetchColumns(), $this->fetchForeignKeys(), $morphs);

        $unmapped = $emitter->unmappedTables($excluded);
        if ($unmapped !== []) {
            $this->error('Unmapped tables (add them to a group in config/diagrams.php):');
            collect($unmapped)->each(fn (string $table) => $this->line("  - {$table}"));

            return self::FAILURE;
        }

        File::ensureDirectoryExists($outputPath);

        $dirty = collect($emitter->emit())
            ->map(fn (string $block, string $key): bool => $this->writeGroupFile(
                $outputPath.'/'.$key.'.md',
                $groups[$key]['title'],
                $block,
                (bool) $this->option('check'),
            ))
            ->filter()
            ->keys();

        if ($this->option('check') && $dirty->isNotEmpty()) {
            $this->error('ERD files out of date: '.$dirty->join(', ').'. Run `make erd`.');

            return self::FAILURE;
        }

        $this->info(sprintf('%d ERD group files %s in %s', count($groups), $this->option('check') ? 'checked' : 'written', $outputPath));

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, array{name: string, type: string, key: string}>>
     */
    private function fetchColumns(): array
    {
        /** @var array<int, object{TABLE_NAME: string, COLUMN_NAME: string, COLUMN_TYPE: string, COLUMN_KEY: string}> $rows */
        $rows = DB::select(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, COLUMN_KEY
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME, ORDINAL_POSITION'
        );

        return collect($rows)
            ->groupBy('TABLE_NAME')
            ->map(static fn ($columns) => $columns->map(static fn (object $column): array => [
                'name' => $column->COLUMN_NAME,
                'type' => $column->COLUMN_TYPE,
                'key' => match ($column->COLUMN_KEY) {
                    'PRI' => 'PK',
                    'MUL', 'UNI' => 'FK',
                    default => '',
                },
            ])->values()->all())
            ->all();
    }

    /**
     * @return array<int, array{table: string, column: string, referenced_table: string}>
     */
    private function fetchForeignKeys(): array
    {
        /** @var array<int, object{TABLE_NAME: string, COLUMN_NAME: string, REFERENCED_TABLE_NAME: string}> $rows */
        $rows = DB::select(
            'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY TABLE_NAME, COLUMN_NAME'
        );

        return collect($rows)
            ->map(static fn (object $fk): array => [
                'table' => $fk->TABLE_NAME,
                'column' => $fk->COLUMN_NAME,
                'referenced_table' => $fk->REFERENCED_TABLE_NAME,
            ])
            ->all();
    }

    /**
     * Writes (or checks) one group file, replacing only the marker block so
     * hand-written prose around it survives. Returns true when the file
     * changed (or would change in --check mode).
     */
    private function writeGroupFile(string $path, string $title, string $block, bool $checkOnly): bool
    {
        $generated = self::MARKER_START."\n".$block."\n".self::MARKER_END;

        $current = File::exists($path) ? File::get($path) : null;

        if ($current !== null && str_contains($current, self::MARKER_START) && str_contains($current, self::MARKER_END)) {
            $pattern = '/'.preg_quote(self::MARKER_START, '/').'.*?'.preg_quote(self::MARKER_END, '/').'/s';
            $next = (string) preg_replace($pattern, $generated, $current);
        } else {
            $next = "# ERD — {$title}\n\n"
                ."> Generated by `make erd`. Edit prose freely — only the marker block is rewritten.\n\n"
                .$generated."\n";
        }

        $changed = $next !== $current;

        if ($changed && ! $checkOnly) {
            File::put($path, $next);
        }

        return $changed;
    }
}
