<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TenantSequence extends Model
{
    protected $fillable = ['tenant_id', 'prefix', 'pattern', 'reset_yearly', 'current_year', 'next_number'];
    protected $casts = ['reset_yearly' => 'boolean'];

    /** Reserve and return the next formatted number (e.g. 'SR-2026-000042'). */
    public function reserve(): string
    {
        return DB::transaction(function () {
            $this->refresh();
            $year = (int) now()->format('Y');

            if ($this->reset_yearly && $this->current_year !== $year) {
                $this->current_year = $year;
                $this->next_number  = 1;
            }

            $number = $this->next_number;
            $this->next_number++;
            $this->save();

            return str_replace(
                ['YYYY', '000001'],
                [$year, str_pad((string) $number, 6, '0', STR_PAD_LEFT)],
                $this->pattern,
            );
        });
    }
}