<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    protected $table = 'quote_items';
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'quote_id','line_no','description','quantity','unit',
        'unit_rate','tax_rate_id','line_total','source_expense_id',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_rate'  => 'decimal:3',
        'line_total' => 'decimal:3',
    ];

    public function quote()   { return $this->belongsTo(Quote::class); }
    public function taxRate() { return $this->belongsTo(TaxRate::class, 'tax_rate_id'); }
}