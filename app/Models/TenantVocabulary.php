<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantVocabulary extends Model
{
    protected $table    = 'tenant_vocabulary';
    protected $fillable = ['tenant_id', 'key', 'singular', 'plural'];
}