<?php

namespace Modules\DataLake\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\DataLake\Models\DataObject;

/**
 * Data Lake (Phase 5): generischer Objektspeicher für PDFs, Bilder, Verträge,
 * CAD-Dateien, Produktionsdaten. Metadaten in DB, Blobs auf dem konfigurierten
 * Disk (local default, S3-kompatibel via filesystems config — R2 cloudunabhängig).
 */
class DataObjectController extends Controller
{
    public function index(Request $request)
    {
        return DataObject::query()
            ->when($request->category, fn ($q, $c) => $q->where('category', $c))
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->when($request->mime, fn ($q, $m) => $q->where('mime_type', 'like', $m.'%'))
            ->latest()
            ->paginate(min(request()->integer('per_page', 200), 200));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'in:'.implode(',', DataObject::CATEGORIES)],
            'file' => ['required', 'file', 'max:204800'],
        ]);

        $file = $request->file('file');
        $tenantId = tenant()->getTenantKey();
        $path = $file->store("data-lake/{$tenantId}", 'local');

        $object = DataObject::create([
            'name' => $validated['name'],
            'category' => $validated['category'] ?? 'other',
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'path' => $path,
            'uploaded_by' => $request->user()?->id,
        ]);

        return response()->json($object, 201);
    }

    public function show(DataObject $dataObject)
    {
        return $dataObject;
    }

    public function download(DataObject $dataObject)
    {
        return Storage::disk($dataObject->disk)->download($dataObject->path, $dataObject->name);
    }

    public function destroy(DataObject $dataObject)
    {
        Storage::disk($dataObject->disk)->delete($dataObject->path);
        $dataObject->delete();

        return response()->noContent();
    }
}
