<?php

declare(strict_types=1);

namespace App\Support\Diagrams;

use InvalidArgumentException;

/**
 * Pure Mermaid `erDiagram` emitter.
 *
 * Takes plain arrays describing the schema (gathered by the diagrams:erd
 * command) plus the group/morph config, and returns one Mermaid code block
 * per group. No IO — fully unit-testable.
 */
final class MermaidErdEmitter
{
    /**
     * @param  array<string, array{title: string, tables: array<int, string>}>  $groups
     * @param  array<string, array<int, array{name: string, type: string, key: string}>>  $columns  table => columns
     * @param  array<int, array{table: string, column: string, referenced_table: string}>  $foreignKeys
     * @param  array<string, array{name: string, targets: array<int, string>}>  $morphs
     */
    public function __construct(
        private readonly array $groups,
        private readonly array $columns,
        private readonly array $foreignKeys,
        private readonly array $morphs,
    ) {}

    /**
     * Tables present in the schema but not mapped to any group (and not excluded).
     *
     * @param  array<int, string>  $excluded
     * @return array<int, string>
     */
    public function unmappedTables(array $excluded): array
    {
        $mapped = collect($this->groups)
            ->flatMap(static fn (array $group): array => $group['tables'])
            ->all();

        return collect(array_keys($this->columns))
            ->reject(static fn (string $table): bool => in_array($table, $mapped, true)
                || in_array($table, $excluded, true))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string> group key => full markdown-ready Mermaid block
     */
    public function emit(): array
    {
        return collect($this->groups)
            ->map(fn (array $group, string $key): string => $this->emitGroup($key, $group))
            ->all();
    }

    /**
     * @param  array{title: string, tables: array<int, string>}  $group
     */
    private function emitGroup(string $key, array $group): string
    {
        $lines = ['```mermaid', 'erDiagram'];

        foreach ($group['tables'] as $table) {
            if (! isset($this->columns[$table])) {
                throw new InvalidArgumentException(
                    "Group [{$key}] lists table [{$table}] which does not exist in the schema. "
                    .'Run migrations or fix config/diagrams.php.'
                );
            }
            $lines = [...$lines, ...$this->emitEntity($table)];
        }

        $lines = [...$lines, ...$this->emitRelations($group['tables'])];
        $lines[] = '```';

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function emitEntity(string $table): array
    {
        $lines = ["    {$table} {"];

        foreach ($this->columns[$table] as $column) {
            $type = $this->mermaidType($column['type']);
            $suffix = $column['key'] !== '' ? ' '.$column['key'] : '';
            $lines[] = "        {$type} {$column['name']}{$suffix}";
        }

        $lines[] = '    }';

        return $lines;
    }

    /**
     * FK edges between tables of the same group are drawn solid; FK edges
     * pointing outside the group become comments so cross-group joins stay
     * discoverable without cluttering the diagram. Configured morphs are
     * drawn dashed to every in-group target.
     *
     * @param  array<int, string>  $groupTables
     * @return array<int, string>
     */
    private function emitRelations(array $groupTables): array
    {
        $lines = [];

        foreach ($this->foreignKeys as $fk) {
            if (! in_array($fk['table'], $groupTables, true)) {
                continue;
            }
            if (in_array($fk['referenced_table'], $groupTables, true)) {
                $lines[] = "    {$fk['referenced_table']} ||--o{ {$fk['table']} : \"{$fk['column']}\"";
            } else {
                $lines[] = "    %% {$fk['table']}.{$fk['column']} -> {$fk['referenced_table']} (other group)";
            }
        }

        foreach ($this->morphs as $table => $morph) {
            if (! in_array($table, $groupTables, true)) {
                continue;
            }
            foreach ($morph['targets'] as $target) {
                if (in_array($target, $groupTables, true)) {
                    $lines[] = "    {$target} ||..o{ {$table} : \"{$morph['name']}\"";
                } else {
                    $lines[] = "    %% {$table}.{$morph['name']} ~> {$target} (polymorphic, other group)";
                }
            }
        }

        return $lines;
    }

    private function mermaidType(string $sqlType): string
    {
        // Mermaid attribute types reject parentheses, commas and spaces —
        // keep only the base type token ("bigint unsigned" → "bigint").
        $base = strtolower(strtok((string) preg_replace('/\(.*/', '', $sqlType), ' ') ?: '');

        return $base === '' ? 'col' : $base;
    }
}
