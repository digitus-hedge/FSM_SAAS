<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\RolePermission;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Currency\BillingTestCase;

/**
 * Roles and permissions as the product works today: each company has the 7 standard roles, seeded from the signed matrix
 * (database/seeders/data/permission_matrix.csv), and User::canDo() reads them from role_permissions.
 * (The old version of this file used Spatie roles and seeders that no longer exist.)
 */
class MemberRolesTest extends BillingTestCase
{
    private const ROLES = ['company_admin', 'operations_manager', 'team_lead', 'field_technician', 'front_desk', 'finance', 'client_user'];

    /** @return array<string, array<string, string>> permission key => role code => level, from the signed matrix */
    private function matrix(): array
    {
        $rows = array_map('str_getcsv', file(database_path('seeders/data/permission_matrix.csv')));
        $head = array_shift($rows);
        $out  = [];
        foreach ($rows as $row) {
            if (count(array_filter($row)) === 0) {
                continue;
            }
            $r = array_combine($head, $row);
            $out[$r['permission_key']] = array_intersect_key($r, array_flip(self::ROLES));
        }

        return $out;
    }

    #[Test]
    public function a_technician_cannot_approve_service_requests_but_can_punch(): void
    {
        $tenant = $this->makeTenant(slug: 'acme');
        $tech   = $this->makeUser($tenant, 'field_technician');

        $this->assertFalse($tech->canDo('sr.approve'));
        $this->assertTrue($tech->canDo('field.punch'));
    }

    #[Test]
    public function every_standard_role_follows_the_signed_matrix_for_every_permission(): void
    {
        $tenant = $this->makeTenant(slug: 'acme');
        $users  = collect(self::ROLES)->mapWithKeys(fn ($code) => [$code => $this->makeUser($tenant, $code)]);

        foreach ($this->matrix() as $key => $levels) {
            foreach (self::ROLES as $code) {
                $this->assertSame($levels[$code] !== 'none', $users[$code]->canDo($key), "{$code} on {$key} (matrix says {$levels[$code]})");
            }
        }
    }

    #[Test]
    public function the_company_admin_has_every_permission_the_matrix_gives_it(): void
    {
        $tenant = $this->makeTenant(slug: 'acme');
        $admin  = $this->makeUser($tenant, 'company_admin');

        $given = collect($this->matrix())->filter(fn ($levels) => $levels['company_admin'] !== 'none')->keys();

        $this->assertGreaterThan(30, $given->count());
        foreach ($given as $key) {
            $this->assertTrue($admin->canDo($key), "Admin should have {$key}");
        }
    }

    #[Test]
    public function a_change_to_one_companys_role_never_reaches_another_company(): void
    {
        $acme  = $this->makeTenant(slug: 'acme');
        $wayne = $this->makeTenant(slug: 'wayne');
        $a = $this->makeUser($acme, 'finance');
        $w = $this->makeUser($wayne, 'finance');
        $quote = Permission::where('key', 'quote.manage')->value('id');

        RolePermission::where('tenant_id', $acme->id)->where('role_id', $a->role_id)->where('permission_id', $quote)->update(['level' => 'none']);

        $this->assertFalse($a->fresh()->canDo('quote.manage'));
        $this->assertTrue($w->fresh()->canDo('quote.manage'));
    }
}
