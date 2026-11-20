<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantVocabulary extends Model
{
    use \App\Models\Concerns\AppTimezoneDates;

    protected $table    = 'tenant_vocabulary';
    protected $fillable = ['tenant_id', 'key', 'singular', 'plural'];
}