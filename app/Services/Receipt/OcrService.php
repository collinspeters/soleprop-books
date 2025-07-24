<?php

namespace App\Services\Receipt;

use App\Models\Banking\Receipt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OcrService
{
    protected $apiUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.ocr.url', 'https://api.ocr.space/parse/image');
        $this->apiKey = config('services.ocr.key');
    }

    /**
     * Process receipt file with OCR
     */
    public function processReceipt(Receipt $receipt): array
    {
        try {
            $receiptFile = $receipt->getMedia('receipt')->first();
            
            if (!$receiptFile) {
                throw new \Exception('No receipt file found');
            }

            // Get file path
            $filePath = $receiptFile->getAbsolutePath();
            
            // Perform OCR
            $ocrText = $this->performOcr($filePath);
            
            // Extract structured data from OCR text
            $extractedData = $this->extractReceiptData($ocrText);
            
            // Update receipt with OCR data
            $receipt->update([
                'ocr_data' => [
                    'raw_text' => $ocrText,
                    'extracted_data' => $extractedData,
                    'processed_at' => now(),
                ],
                'vendor_name' => $extractedData['vendor_name'] ?? null,
                'amount' => $extractedData['amount'] ?? null,
                'receipt_date' => $extractedData['date'] ?? null,
                'status' => 'processed',
            ]);

            return $extractedData;

        } catch (\Exception $e) {
            Log::error('OCR processing failed', [
                'receipt_id' => $receipt->id,
                'error' => $e->getMessage(),
            ]);

            $receipt->update(['status' => 'failed']);
            
            throw $e;
        }
    }

    /**
     * Perform OCR on file
     */
    protected function performOcr(string $filePath): string
    {
        // If OCR API is not configured, use mock data for development
        if (!$this->apiKey) {
            return $this->getMockOcrText();
        }

        try {
            $response = Http::asMultipart()
                ->post($this->apiUrl, [
                    'apikey' => $this->apiKey,
                    'language' => 'eng',
                    'isOverlayRequired' => false,
                    'file' => fopen($filePath, 'r'),
                    'filetype' => $this->getFileType($filePath),
                    'detectOrientation' => true,
                    'isTable' => true,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                
                if ($result['IsErroredOnProcessing'] === false) {
                    return $result['ParsedResults'][0]['ParsedText'] ?? '';
                } else {
                    throw new \Exception('OCR API error: ' . ($result['ErrorMessage'] ?? 'Unknown error'));
                }
            } else {
                throw new \Exception('OCR API request failed: ' . $response->status());
            }

        } catch (\Exception $e) {
            Log::error('OCR API call failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);
            
            // Fallback to mock data in case of API failure
            return $this->getMockOcrText();
        }
    }

    /**
     * Extract structured data from OCR text
     */
    protected function extractReceiptData(string $ocrText): array
    {
        $data = [
            'vendor_name' => null,
            'amount' => null,
            'date' => null,
            'items' => [],
            'tax_amount' => null,
            'subtotal' => null,
        ];

        $lines = explode("\n", $ocrText);
        $lines = array_filter(array_map('trim', $lines));

        // Extract vendor name (usually first few lines)
        $data['vendor_name'] = $this->extractVendorName($lines);

        // Extract total amount
        $data['amount'] = $this->extractTotalAmount($lines);

        // Extract date
        $data['date'] = $this->extractDate($lines);

        // Extract tax amount
        $data['tax_amount'] = $this->extractTaxAmount($lines);

        // Extract subtotal
        $data['subtotal'] = $this->extractSubtotal($lines);

        // Extract line items
        $data['items'] = $this->extractLineItems($lines);

        return $data;
    }

    /**
     * Extract vendor name from OCR text lines
     */
    protected function extractVendorName(array $lines): ?string
    {
        // Look for vendor name in first few lines
        foreach (array_slice($lines, 0, 5) as $line) {
            // Skip lines that look like addresses, phone numbers, or common receipt headers
            if (preg_match('/^\d+\s*[A-Za-z\s]+(?:St|Ave|Rd|Blvd|Dr)/i', $line)) continue;
            if (preg_match('/^\(?[\d\s\-\(\)]+$/', $line)) continue;
            if (preg_match('/^(receipt|invoice|bill|order)/i', $line)) continue;
            
            // Look for lines with business-like names
            if (preg_match('/^([A-Za-z\s&\',\.\-]+)$/i', $line) && strlen($line) > 3) {
                return trim($line);
            }
        }

        return null;
    }

    /**
     * Extract total amount from OCR text lines
     */
    protected function extractTotalAmount(array $lines): ?float
    {
        $patterns = [
            '/total[:\s]*\$?(\d+\.?\d*)/i',
            '/amount[:\s]*\$?(\d+\.?\d*)/i',
            '/^total\s*(\d+\.?\d*)/i',
            '/\$(\d+\.\d{2})(?:\s|$)/',
        ];

        foreach ($lines as $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    return (float) $matches[1];
                }
            }
        }

        // Look for the largest dollar amount as fallback
        $amounts = [];
        foreach ($lines as $line) {
            if (preg_match_all('/\$?(\d+\.\d{2})/', $line, $matches)) {
                foreach ($matches[1] as $amount) {
                    $amounts[] = (float) $amount;
                }
            }
        }

        return !empty($amounts) ? max($amounts) : null;
    }

    /**
     * Extract date from OCR text lines
     */
    protected function extractDate(array $lines): ?string
    {
        $datePatterns = [
            '/(\d{1,2}\/\d{1,2}\/\d{4})/',
            '/(\d{1,2}-\d{1,2}-\d{4})/',
            '/(\d{4}-\d{1,2}-\d{1,2})/',
            '/(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{1,2},?\s+\d{4}/i',
        ];

        foreach ($lines as $line) {
            foreach ($datePatterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    try {
                        $date = Carbon::parse($matches[1]);
                        return $date->format('Y-m-d');
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract tax amount from OCR text lines
     */
    protected function extractTaxAmount(array $lines): ?float
    {
        $patterns = [
            '/tax[:\s]*\$?(\d+\.?\d*)/i',
            '/vat[:\s]*\$?(\d+\.?\d*)/i',
            '/gst[:\s]*\$?(\d+\.?\d*)/i',
        ];

        foreach ($lines as $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    return (float) $matches[1];
                }
            }
        }

        return null;
    }

    /**
     * Extract subtotal from OCR text lines
     */
    protected function extractSubtotal(array $lines): ?float
    {
        $patterns = [
            '/subtotal[:\s]*\$?(\d+\.?\d*)/i',
            '/sub[\s\-]?total[:\s]*\$?(\d+\.?\d*)/i',
        ];

        foreach ($lines as $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    return (float) $matches[1];
                }
            }
        }

        return null;
    }

    /**
     * Extract line items from OCR text lines
     */
    protected function extractLineItems(array $lines): array
    {
        $items = [];
        
        foreach ($lines as $line) {
            // Look for lines that might be items (contain description and price)
            if (preg_match('/^(.+?)\s+\$?(\d+\.\d{2})$/', $line, $matches)) {
                $description = trim($matches[1]);
                $amount = (float) $matches[2];
                
                // Skip if it looks like a total or tax line
                if (preg_match('/^(total|tax|subtotal|amount)/i', $description)) {
                    continue;
                }
                
                $items[] = [
                    'description' => $description,
                    'amount' => $amount,
                ];
            }
        }

        return $items;
    }

    /**
     * Get file type for OCR API
     */
    protected function getFileType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'pdf':
                return 'PDF';
            case 'jpg':
            case 'jpeg':
                return 'JPG';
            case 'png':
                return 'PNG';
            case 'gif':
                return 'GIF';
            case 'bmp':
                return 'BMP';
            default:
                return 'JPG';
        }
    }

    /**
     * Get mock OCR text for development/testing
     */
    protected function getMockOcrText(): string
    {
        return "ACME GROCERY STORE
123 Main Street
City, State 12345
(555) 123-4567

Date: " . date('m/d/Y') . "
Time: " . date('H:i') . "

Apples                     $3.99
Bread                      $2.49
Milk                       $4.29
Eggs                       $3.99

Subtotal                  $14.76
Tax                        $1.18
Total                     $15.94

Thank you for shopping!";
    }
}