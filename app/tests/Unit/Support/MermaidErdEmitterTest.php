<?php

declare(strict_types=1);

use App\Support\Diagrams\MermaidErdEmitter;

function emitterFixture(): MermaidErdEmitter
{
    return new MermaidErdEmitter(
        groups: [
            '01-people' => ['title' => 'People', 'tables' => ['users', 'profiles']],
            '02-money' => ['title' => 'Money', 'tables' => ['invoices']],
        ],
        columns: [
            'users' => [
                ['name' => 'id', 'type' => 'bigint unsigned', 'key' => 'PK'],
                ['name' => 'name', 'type' => 'varchar(255)', 'key' => ''],
            ],
            'profiles' => [
                ['name' => 'id', 'type' => 'bigint unsigned', 'key' => 'PK'],
                ['name' => 'user_id', 'type' => 'bigint unsigned', 'key' => 'FK'],
            ],
            'invoices' => [
                ['name' => 'id', 'type' => 'bigint unsigned', 'key' => 'PK'],
                ['name' => 'school_id', 'type' => 'bigint unsigned', 'key' => 'FK'],
            ],
            'orphans' => [
                ['name' => 'id', 'type' => 'bigint unsigned', 'key' => 'PK'],
            ],
        ],
        foreignKeys: [
            ['table' => 'profiles', 'column' => 'user_id', 'referenced_table' => 'users'],
            ['table' => 'invoices', 'column' => 'school_id', 'referenced_table' => 'schools'],
        ],
        morphs: [
            'invoices' => ['name' => 'payable', 'targets' => ['users', 'invoices']],
        ],
    );
}

it('emits one mermaid block per group with entities and column types', function (): void {
    $blocks = emitterFixture()->emit();

    expect($blocks)->toHaveKeys(['01-people', '02-money'])
        ->and($blocks['01-people'])->toStartWith('```mermaid')
        ->and($blocks['01-people'])->toContain('users {')
        ->and($blocks['01-people'])->toContain('bigint id PK')
        ->and($blocks['01-people'])->toContain('varchar name')
        ->and($blocks['01-people'])->toEndWith('```');
});

it('draws in-group foreign keys as solid edges', function (): void {
    $blocks = emitterFixture()->emit();

    expect($blocks['01-people'])->toContain('users ||--o{ profiles : "user_id"');
});

it('renders cross-group foreign keys as comments instead of edges', function (): void {
    $blocks = emitterFixture()->emit();

    expect($blocks['02-money'])->toContain('%% invoices.school_id -> schools (other group)')
        ->and($blocks['02-money'])->not->toContain('schools ||--o{');
});

it('draws configured polymorphic relations as dashed in-group edges and comments otherwise', function (): void {
    $blocks = emitterFixture()->emit();

    expect($blocks['02-money'])->toContain('invoices ||..o{ invoices : "payable"')
        ->and($blocks['02-money'])->toContain('%% invoices.payable ~> users (polymorphic, other group)');
});

it('reports schema tables missing from every group and the exclude list', function (): void {
    expect(emitterFixture()->unmappedTables([]))->toBe(['orphans'])
        ->and(emitterFixture()->unmappedTables(['orphans']))->toBe([]);
});

it('throws when a group lists a table that is not in the schema', function (): void {
    $emitter = new MermaidErdEmitter(
        groups: ['01-x' => ['title' => 'X', 'tables' => ['ghost_table']]],
        columns: [],
        foreignKeys: [],
        morphs: [],
    );

    expect(fn () => $emitter->emit())->toThrow(InvalidArgumentException::class, 'ghost_table');
});
