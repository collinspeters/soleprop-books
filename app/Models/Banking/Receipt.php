<?php

namespace App\Models\Banking;

use App\Abstracts\Model;
use App\Traits\Media;
use Bkwld\Cloner\Cloneable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Plank\Mediable\Mediable;

class Receipt extends Model
{
    use Cloneable, Media, Mediable, SoftDeletes;

    protected $table = 'receipts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'transaction_id',
        'vendor_name',
        'amount',
        'currency_code',
        'receipt_date',
        'category_id',
        'description',
        'status',
        'ocr_data',
        'ai_category_confidence',
        'created_from',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'receipt_date' => 'date',
        'amount' => 'double',
        'ocr_data' => 'array',
        'ai_category_confidence' => 'float',
        'deleted_at' => 'datetime',
    ];

    /**
     * Sortable columns.
     *
     * @var array
     */
    public $sortable = ['vendor_name', 'amount', 'receipt_date', 'status'];

    /**
     * Searchable rules.
     *
     * @var array
     */
    protected $searchableFields = [
        'vendor_name' => 'like',
        'description' => 'like',
        'amount' => 'like',
    ];

    /**
     * Get the transaction that owns the receipt.
     */
    public function transaction()
    {
        return $this->belongsTo('App\Models\Banking\Transaction');
    }

    /**
     * Get the category that owns the receipt.
     */
    public function category()
    {
        return $this->belongsTo('App\Models\Setting\Category');
    }

    /**
     * Get the company that owns the receipt.
     */
    public function company()
    {
        return $this->belongsTo('App\Models\Common\Company');
    }

    /**
     * Get the receipt file.
     */
    public function getReceiptFileAttribute()
    {
        return $this->getMedia('receipt')->first();
    }

    /**
     * Check if receipt has been processed by OCR.
     */
    public function isProcessed()
    {
        return !empty($this->ocr_data);
    }

    /**
     * Check if receipt is matched to a transaction.
     */
    public function isMatched()
    {
        return !is_null($this->transaction_id);
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute()
    {
        $statuses = [
            'pending' => 'Pending Processing',
            'processed' => 'Processed',
            'matched' => 'Matched to Transaction',
            'failed' => 'Processing Failed',
        ];

        return $statuses[$this->status] ?? 'Unknown';
    }

    /**
     * Scope to get unmatched receipts.
     */
    public function scopeUnmatched($query)
    {
        return $query->whereNull('transaction_id');
    }

    /**
     * Scope to get processed receipts.
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    /**
     * Scope to get receipts within date range.
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('receipt_date', [$startDate, $endDate]);
    }
}
