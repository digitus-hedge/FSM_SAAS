<?php
namespace App\Services\Users;

use App\Mail\InvitationMail;
use App\Models\{Role, Tenant, User};
use App\Services\Audit\AuditLogger;
use App\Services\Auth\SecureLinkService;
use Illuminate\Support\Facades\{DB, Hash, Log, Mail};
use Illuminate\Support\Str;

class UserCsvImporter
{
    private const MAX_ROWS = 500;

    public function __construct(
        private SecureLinkService $links,
        private AuditLogger $audit,
    ) {}

    public function parse(string $path): array
    {
        if (! is_readable($path)) {
            throw new \RuntimeException('Cannot read uploaded CSV.');
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new \RuntimeException('CSV is empty.');
        }

        $header = array_map(fn($h) => strtolower(trim($h, " \t\n\r\0\x0B\xEF\xBB\xBF")), $header);

        $required = ['name', 'email', 'role'];
        foreach ($required as $col) {
            if (! in_array($col, $header, true)) {
                fclose($handle);
                throw new \RuntimeException("CSV is missing the '{$col}' column.");
            }
        }

        $rows = [];
        $num  = 1;
        while (($raw = fgetcsv($handle)) !== false) {
            if (count(array_filter($raw, fn($v) => trim((string) $v) !== '')) === 0) continue;
            $raw = array_pad($raw, count($header), '');
            $data = array_combine($header, array_map(fn($v) => trim((string) $v), array_slice($raw, 0, count($header))));
            $rows[] = ['row_num' => $num++, 'data' => $data];
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);
                throw new \RuntimeException('CSV exceeds 500 rows. Split and upload in batches.');
            }
        }
        fclose($handle);
        return $rows;
    }

    public function validate(array $rows, Tenant $tenant): array
    {
        $roles = Role::where('tenant_id', $tenant->id)->pluck('id', 'code')->toArray();

        $existing = User::where('tenant_id', $tenant->id)
            ->pluck('email')->map(fn($e) => mb_strtolower($e))->toArray();

        $leads = User::where('tenant_id', $tenant->id)
            ->whereHas('role', fn($q) => $q->where('code', 'team_lead'))
            ->pluck('id', 'email')->toArray();
        $leads = array_change_key_case($leads, CASE_LOWER);

        $seen = [];
        $out  = [];
        foreach ($rows as $r) {
            $d = $r['data'];
            $errors = [];
            $name  = $d['name'] ?? '';
            $email = mb_strtolower($d['email'] ?? '');
            $role  = $d['role'] ?? '';
            $leadEmail = mb_strtolower($d['team_lead_email'] ?? '');

            if (! $name || mb_strlen($name) < 2 || mb_strlen($name) > 150) {
                $errors[] = 'Name is required (2–150 characters).';
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format.';
            } elseif (in_array($email, $existing, true)) {
                $errors[] = 'Email already exists in this workspace.';
            } elseif (in_array($email, $seen, true)) {
                $errors[] = 'Duplicate email in this CSV.';
            }
            if (! $role) {
                $errors[] = 'Role is required.';
                $roleId = null;
            } elseif (! isset($roles[$role])) {
                $errors[] = "Role '{$role}' does not exist in this workspace.";
                $roleId = null;
            } else {
                $roleId = $roles[$role];
            }

            $leadId = null;
            if ($leadEmail !== '') {
                if (! isset($leads[$leadEmail])) {
                    $errors[] = "Team lead '{$leadEmail}' not found.";
                } else {
                    $leadId = $leads[$leadEmail];
                }
            }

            if (! $errors) $seen[] = $email;

            $out[] = [
                'row_num'      => $r['row_num'],
                'data'         => $d,
                'errors'       => $errors,
                'role_id'      => $roleId,
                'team_lead_id' => $leadId,
            ];
        }
        return $out;
    }

    public function import(array $validated, Tenant $tenant, $actor, bool $skipErrors = true): array
    {
        $created = $invited = $skipped = 0;
        $failed  = [];

        $host   = parse_url(config('app.url'), PHP_URL_HOST);
        $port   = parse_url(config('app.url'), PHP_URL_PORT);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $portPart = $port ? ":{$port}" : '';

        DB::transaction(function () use ($validated, $tenant, $skipErrors, $host, $portPart, $scheme, &$created, &$invited, &$skipped, &$failed) {
            foreach ($validated as $r) {
                if ($r['errors']) {
                    if ($skipErrors) { $skipped++; continue; }
                    throw new \RuntimeException("Row {$r['row_num']} has errors; aborting import.");
                }

                $d = $r['data'];
                try {
                    $user = User::create([
                        'tenant_id'            => $tenant->id,
                        'role_id'              => $r['role_id'],
                        'name'                 => $d['name'],
                        'email'                => mb_strtolower($d['email']),
                        'phone'                => $d['phone'] ?? null ?: null,
                        'employee_code'        => $d['employee_code'] ?? null ?: null,
                        'team_lead_id'         => $r['team_lead_id'],
                        'password'             => Hash::make(Str::random(32)),
                        'must_change_password' => true,
                    ]);
                    $created++;

                    $token = $this->links->create($user, 'user_invite', 7 * 24 * 60);
                    $url   = "{$scheme}://{$tenant->slug}.{$host}{$portPart}/invitation/{$token}";

                    try {
                        Mail::to($user->email)->send((new InvitationMail($user, $tenant, $url))->afterCommit());
                        $invited++;
                    } catch (\Throwable $e) {
                        Log::warning("CSV import: invitation email failed for {$user->email}: {$e->getMessage()}");
                    }
                } catch (\Throwable $e) {
                    $failed[] = $r['row_num'];
                    Log::error("CSV import: user create failed row {$r['row_num']}: {$e->getMessage()}");
                }
            }
        });

        $this->audit->log('user.bulk_import', [
            'item_type' => User::class,
            'tenant_id' => $tenant->id,
            'new' => compact('created', 'invited', 'skipped', 'failed'),
        ]);

        return compact('created', 'invited', 'skipped', 'failed');
    }
}