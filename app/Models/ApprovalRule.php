<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Extra approval settings of one service category (FRM-08). The mode itself is service_categories.approval_mode. */
class ApprovalRule extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'service_category_id', 'mode', 'amount_limit_base', 'second_approver_user_id'];

    protected $casts = ['amount_limit_base' => 'decimal:3'];

    public function category() { return $this->belongsTo(ServiceCategory::class, 'service_category_id'); }
}
