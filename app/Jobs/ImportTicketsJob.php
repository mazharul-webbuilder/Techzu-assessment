<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Import;
use App\Models\Ticket;
use App\Services\Common\TicketService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Background job processing uploaded CSV files row-by-row with flat memory usage.
 *
 * Implements:
 * - fopen / fgetcsv streaming read (O(1) memory)
 * - Row validation with detailed error reporting
 * - Deduplication via external_id (in-memory + database)
 * - Safe re-import idempotency
 * - Periodic progress persistence
 */
class ImportTicketsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout for processing large batch imports (in seconds).
     */
    public int $timeout = 1800;

    public function __construct(
        public Import $import
    ) {}

    /**
     * Execute the batch import.
     */
    public function handle(TicketService $ticketService): void
    {
        $this->import->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $disk = config('helpdesk.attachments.disk', 'local');
        $csvFullPath = Storage::disk($disk)->path($this->import->file_path);

        if (!file_exists($csvFullPath) || !is_readable($csvFullPath)) {
            $this->import->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);
            Log::error("Import #{$this->import->id} aborted: file {$this->import->file_path} not found or unreadable.");
            return;
        }

        $fileHandle = fopen($csvFullPath, 'r');
        if ($fileHandle === false) {
            $this->import->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);
            return;
        }

        $errorRelativePath = "imports/{$this->import->id}/errors.csv";
        $errorFullPath = Storage::disk($disk)->path($errorRelativePath);
        $errorDir = dirname($errorFullPath);
        if (!is_dir($errorDir)) {
            mkdir($errorDir, 0755, true);
        }

        $errorHandle = fopen($errorFullPath, 'w');
        fputcsv($errorHandle, ['row_number', 'error_reason', 'raw_data']);

        $seenExternalIds = [];
        $processedRows = 0;
        $failedRows = 0;
        $skippedDuplicates = 0;
        $rowNumber = 0;

        try {
            // Read and normalize headers
            $rawHeader = fgetcsv($fileHandle);
            if ($rawHeader === false || empty($rawHeader)) {
                fclose($fileHandle);
                fclose($errorHandle);
                if (file_exists($errorFullPath)) {
                    @unlink($errorFullPath);
                }
                $this->import->update([
                    'status' => 'completed',
                    'total_rows' => 0,
                    'processed_rows' => 0,
                    'failed_rows' => 0,
                    'skipped_duplicates' => 0,
                    'finished_at' => now(),
                ]);
                return;
            }

            // Remove UTF-8 BOM if present on first header column
            $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', $rawHeader[0]);

            $headers = array_map(function ($h) {
                return strtolower(trim(str_replace([' ', '-'], '_', (string) $h)));
            }, $rawHeader);

            $rowNumber = 1; // Header is row 1

            while (($row = fgetcsv($fileHandle)) !== false) {
                $rowNumber++;

                // Skip blank lines
                if ($row === [null] || empty(array_filter($row, fn ($val) => $val !== null && trim($val) !== ''))) {
                    continue;
                }

                $rawRowString = implode(',', $row);

                if (count($row) !== count($headers)) {
                    $failedRows++;
                    fputcsv($errorHandle, [$rowNumber, 'Column count mismatch with header', $rawRowString]);
                    continue;
                }

                $rowAssoc = array_combine($headers, $row);

                // Check external_id for duplicates before validation
                $externalId = !empty($rowAssoc['external_id']) ? trim($rowAssoc['external_id']) : null;
                if ($externalId !== null && $externalId !== '') {
                    if (isset($seenExternalIds[$externalId]) || Ticket::where('external_id', $externalId)->exists()) {
                        $skippedDuplicates++;
                        continue;
                    }
                    $seenExternalIds[$externalId] = true;
                }

                // Validate row fields
                $validator = Validator::make($rowAssoc, [
                    'customer_email' => ['required', 'email'],
                    'subject' => ['required', 'string', 'max:255'],
                    'description' => ['required', 'string'],
                    'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
                    'category' => ['required', 'string', 'max:100'],
                    'status' => ['required', 'string', Rule::in(['open', 'in_progress', 'waiting_for_customer', 'resolved', 'closed'])],
                    'created_at' => ['required', 'date'],
                ]);

                if ($validator->fails()) {
                    $failedRows++;
                    $errorReason = implode('; ', $validator->errors()->all());
                    fputcsv($errorHandle, [$rowNumber, $errorReason, $rawRowString]);
                    continue;
                }

                // Resolve customer by email (create if missing)
                $customerEmail = strtolower(trim($rowAssoc['customer_email']));
                $customerName = !empty($rowAssoc['customer_name']) && trim($rowAssoc['customer_name']) !== ''
                    ? trim($rowAssoc['customer_name'])
                    : explode('@', $customerEmail)[0];

                $customer = Customer::firstOrCreate(
                    ['email' => $customerEmail],
                    [
                        'name' => $customerName,
                        'password' => bcrypt(\Illuminate\Support\Str::random(24)),
                    ]
                );

                // Resolve category by name (create if missing)
                $categoryName = trim($rowAssoc['category']);
                $category = Category::firstOrCreate(
                    ['name' => $categoryName],
                    ['is_active' => true]
                );

                // Create ticket in silent mode
                $ticketService->createFromImport(
                    customer: $customer,
                    category: $category,
                    data: [
                        'external_id' => $externalId,
                        'subject' => trim($rowAssoc['subject']),
                        'description' => trim($rowAssoc['description']),
                        'priority' => strtolower(trim($rowAssoc['priority'])),
                        'status' => strtolower(trim($rowAssoc['status'])),
                        'created_at' => Carbon::parse($rowAssoc['created_at']),
                    ],
                    actor: $this->import->admin
                );

                $processedRows++;

                // Periodically update progress every 500 rows to keep memory and DB load minimal
                if (($processedRows + $failedRows + $skippedDuplicates) % 500 === 0) {
                    $this->import->update([
                        'total_rows' => $rowNumber - 1,
                        'processed_rows' => $processedRows,
                        'failed_rows' => $failedRows,
                        'skipped_duplicates' => $skippedDuplicates,
                    ]);
                }
            }

            fclose($fileHandle);
            fclose($errorHandle);

            $errorReportFinalPath = null;
            if ($failedRows > 0) {
                $errorReportFinalPath = $errorRelativePath;
            } elseif (file_exists($errorFullPath)) {
                @unlink($errorFullPath);
            }

            $this->import->update([
                'status' => 'completed',
                'total_rows' => ($rowNumber > 0) ? ($rowNumber - 1) : 0,
                'processed_rows' => $processedRows,
                'failed_rows' => $failedRows,
                'skipped_duplicates' => $skippedDuplicates,
                'error_report_path' => $errorReportFinalPath,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            if (is_resource($fileHandle)) {
                fclose($fileHandle);
            }
            if (is_resource($errorHandle)) {
                fclose($errorHandle);
            }

            Log::error("Import #{$this->import->id} unhandled exception: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $this->import->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }
}
