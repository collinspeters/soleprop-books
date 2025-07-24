<?php

namespace App\Http\Resources\Banking;

use Illuminate\Http\Resources\Json\JsonResource;

class Receipt extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'vendor_name' => $this->vendor_name,
            'amount' => $this->amount,
            'currency_code' => $this->currency_code,
            'receipt_date' => $this->receipt_date?->format('Y-m-d'),
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'ai_category_confidence' => $this->ai_category_confidence,
            'created_from' => $this->created_from,
            'is_processed' => $this->isProcessed(),
            'is_matched' => $this->isMatched(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Relationships
            'transaction' => $this->whenLoaded('transaction', function () {
                return [
                    'id' => $this->transaction->id,
                    'description' => $this->transaction->description,
                    'amount' => $this->transaction->amount,
                    'paid_at' => $this->transaction->paid_at->format('Y-m-d'),
                    'type' => $this->transaction->type,
                ];
            }),
            
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'color' => $this->category->color,
                ];
            }),
            
            'media' => $this->whenLoaded('media', function () {
                $receiptFile = $this->getMedia('receipt')->first();
                if ($receiptFile) {
                    return [
                        'id' => $receiptFile->id,
                        'filename' => $receiptFile->filename,
                        'mime_type' => $receiptFile->mime_type,
                        'size' => $receiptFile->size,
                        'url' => route('api.receipts.download', $this->id),
                    ];
                }
                return null;
            }),
            
            // OCR data (only include summary for API response)
            'ocr_summary' => $this->when($this->ocr_data, function () {
                $ocrData = $this->ocr_data;
                $extractedData = $ocrData['extracted_data'] ?? [];
                
                return [
                    'processed_at' => $ocrData['processed_at'] ?? null,
                    'vendor_name' => $extractedData['vendor_name'] ?? null,
                    'amount' => $extractedData['amount'] ?? null,
                    'date' => $extractedData['date'] ?? null,
                    'tax_amount' => $extractedData['tax_amount'] ?? null,
                    'subtotal' => $extractedData['subtotal'] ?? null,
                    'items_count' => count($extractedData['items'] ?? []),
                ];
            }),
        ];
    }
}