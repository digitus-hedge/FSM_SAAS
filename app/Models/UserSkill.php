<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSkill extends Model
{
    use \App\Models\Concerns\AppTimezoneDates;

    protected $fillable = ['tenant_id', 'user_id', 'service_category_id'];
}