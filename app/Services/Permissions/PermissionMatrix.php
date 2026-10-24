<?php
namespace App\Services\Permissions;

class PermissionMatrix
{
    private const CSV_PATH = 'seeders/data/permission_matrix.csv';

    private const ROLE_COLUMNS = [
        'company_admin', 'operations_manager', 'team_lead',
        'field_technician', 'front_desk', 'finance', 'client_user',
    ];

    /** Display order, least to most permissive. */
    public const LEVEL_ORDER = [
        'none', 'view_own', 'view_only', 'edit_own', 'record_level', 'full', 'grantable',
    ];

    public const LEVEL_LABELS = [
        'none'         => 'Off',
        'view_own'     => 'View own',
        'view_only'    => 'View only',
        'edit_own'     => 'Edit own',
        'record_level' => 'Scoped',
        'full'         => 'Full',
        'grantable'    => 'Grantable',
    ];

    private ?array $rows = null;
    private ?array $header = null;

    private function load(): void
    {
        if ($this->rows !== null) return;

        $csv = database_path(self::CSV_PATH);
        $raw = array_map('str_getcsv', file($csv));
        $this->header = array_shift($raw);
        $this->rows = array_values(array_filter(
            $raw,
            fn($row) => count(array_filter($row, fn($v) => $v !== '')) > 0
        ));
    }

    /** @return array<string, string> permission_key => level for this role */
    public function defaultsForRole(string $roleCode): array
    {
        $this->load();
        if (! in_array($roleCode, $this->header, true)) return [];

        $out = [];
        foreach ($this->rows as $row) {
            $r = array_combine($this->header, $row);
            $out[$r['permission_key']] = $r[$roleCode];
        }
        return $out;
    }

    /**
     * Levels this permission takes anywhere in the matrix, plus 'none'.
     * Sorted by LEVEL_ORDER.
     */
    public function allowedLevelsForPermission(string $permKey): array
    {
        $this->load();

        $levels = ['none'];
        foreach ($this->rows as $row) {
            $r = array_combine($this->header, $row);
            if ($r['permission_key'] !== $permKey) continue;
            foreach (self::ROLE_COLUMNS as $roleCode) {
                $lvl = $r[$roleCode];
                if ($lvl !== 'none' && ! in_array($lvl, $levels, true)) {
                    $levels[] = $lvl;
                }
            }
        }

        return array_values(array_intersect(self::LEVEL_ORDER, $levels));
    }

    public function scopeNoteFor(string $permKey): ?string
    {
        $this->load();
        foreach ($this->rows as $row) {
            $r = array_combine($this->header, $row);
            if ($r['permission_key'] === $permKey) {
                return $r['scope_note'] !== '' ? $r['scope_note'] : null;
            }
        }
        return null;
    }
}