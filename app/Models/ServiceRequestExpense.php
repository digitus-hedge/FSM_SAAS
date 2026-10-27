<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequestExpense extends Model
{
    protected $table = 'service_request_expenses';

    // Table has no updated_at column, so disable timestamps
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'service_request_id', 'asset_id',
        'logged_by_user_id', 'client_event_uuid',
        'vendor_id', 'expense_category_id',
        'description', 'quantity', 'unit_rate', 'total_amount',
        'is_billable_to_client', 'receipt_attachment_id',
        'approval_status',
        'approved_by_user_id', 'approved_at',
        'rejected_by_user_id', 'rejected_at', 'rejected_reason',
    ];

    protected $casts = [
        'quantity'              => 'decimal:2',
        'unit_rate'             => 'decimal:2',
        'total_amount'          => 'decimal:2',
        'is_billable_to_client' => 'boolean',
        'approved_at'           => 'datetime',
        'rejected_at'           => 'datetime',
    ];

    /* ===================== RELATIONS ===================== */

    public function category()       { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); }
    public function receipt()        { return $this->belongsTo(Attachment::class, 'receipt_attachment_id'); }
    public function loggedBy()       { return $this->belongsTo(User::class, 'logged_by_user_id'); }
    public function serviceRequest() { return $this->belongsTo(ServiceRequest::class); }
    public function approvedBy()     { return $this->belongsTo(User::class, 'approved_by_user_id'); }
    public function rejectedBy()     { return $this->belongsTo(User::class, 'rejected_by_user_id'); }

    /* ===================== SCOPES ===================== */

    public function scopeWaiting($q)  { return $q->where('approval_status', 'pending'); }
    public function scopeApproved($q) { return $q->where('approval_status', 'approved'); }
    public function scopeRejected($q) { return $q->where('approval_status', 'rejected'); }
}