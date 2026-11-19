<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id', 'line_no', 'description', 'quantity', 'unit',
        'unit_rate', 'tax_rate_id', 'line_total', 'source_expense_id', 'converted_at_rate',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_rate'  => 'decimal:3',
        'line_total' => 'decimal:3',
        'converted_at_rate' => 'decimal:8',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function taxRate() { return $this->belongsTo(TaxRate::class, 'tax_rate_id'); }
}