<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportTicketsRequest;
use App\Jobs\ImportTicketsJob;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    /**
     * Display a paginated listing of past and current imports.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Import::class);

        $imports = Import::query()
            ->with('admin:id,name,email')
            ->latest()
            ->paginate(15);

        return view('admin.imports.index', compact('imports'));
    }

    /**
     * Show the ticket CSV upload form.
     */
    public function create(): View
    {
        $this->authorize('create', Import::class);

        return view('admin.imports.create');
    }

    /**
     * Store and queue a new CSV file import job.
     */
    public function store(ImportTicketsRequest $request): RedirectResponse
    {
        $this->authorize('create', Import::class);

        $file = $request->file('file');
        $disk = config('helpdesk.attachments.disk', 'local');
        $filename = Str::uuid()->toString() . '.csv';
        $path = $file->storeAs('imports', $filename, $disk);

        $import = Import::create([
            'admin_id' => $request->user('admin')->id,
            'file_path' => $path,
            'status' => 'pending',
        ]);

        ImportTicketsJob::dispatch($import);

        return redirect()
            ->route('admin.imports.show', $import)
            ->with('success', 'Ticket import file uploaded successfully and dispatched for background processing.');
    }

    /**
     * Display the status, counters, and error summary for a specific import.
     */
    public function show(Import $import): View
    {
        $this->authorize('view', $import);

        $import->load('admin:id,name,email');

        return view('admin.imports.show', compact('import'));
    }

    /**
     * Download the error CSV report for an import if failures occurred.
     */
    public function downloadErrors(Import $import): StreamedResponse
    {
        $this->authorize('view', $import);

        $disk = config('helpdesk.attachments.disk', 'local');

        if (!$import->error_report_path || !Storage::disk($disk)->exists($import->error_report_path)) {
            abort(404, 'No error report available for this import.');
        }

        return Storage::disk($disk)->download(
            $import->error_report_path,
            "import-{$import->id}-errors.csv",
            ['Content-Type' => 'text/csv']
        );
    }
}
