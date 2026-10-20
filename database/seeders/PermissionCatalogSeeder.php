<?php
// database/seeders/PermissionCatalogSeeder.php
namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $csv = database_path('seeders/data/permission_matrix.csv');

        if (! is_file($csv)) {
            throw new \RuntimeException("Signed permission matrix not found at {$csv}");
        }

        $rows = array_map('str_getcsv', file($csv));
        $header = array_shift($rows);

        foreach ($rows as $row) {
            if (count(array_filter($row)) === 0) continue; // skip blank lines
            $r = array_combine($header, $row);

            Permission::updateOrCreate(
                ['key' => $r['permission_key']],
                [
                    'group'       => $r['group'],
                    'description' => $r['description'],
                    // feature_required stays null until the next matrix revision carries it;
                    // see decisions.md flag from earlier turn.
                    'feature_required' => null,
                ],
            );
        }
    }
}