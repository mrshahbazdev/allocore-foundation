<?php

namespace Modules\Documents\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Models\Document;
use Modules\Documents\Models\DocumentVersion;

class DocumentController extends Controller
{
    public function index()
    {
        return Document::with('currentVersion')->paginate();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $document = Document::create([
            'title' => $validated['title'],
            'category' => $validated['category'] ?? null,
            'uploaded_by' => $request->user()?->id,
        ]);

        $this->storeVersion($request->file('file'), $document, $request);

        return response()->json($document->load('versions'), 201);
    }

    public function show(Document $document)
    {
        return $document->load('versions');
    }

    public function uploadVersion(Request $request, Document $document)
    {
        $request->validate(['file' => ['required', 'file', 'max:51200']]);

        $version = $this->storeVersion($request->file('file'), $document, $request);

        return response()->json($version, 201);
    }

    public function download(Document $document, ?DocumentVersion $version = null)
    {
        $version ??= $document->currentVersion;
        abort_unless($version, 404);

        return Storage::disk('local')->download($version->path, $version->original_name);
    }

    public function destroy(Document $document)
    {
        foreach ($document->versions as $version) {
            Storage::disk('local')->delete($version->path);
        }
        $document->delete();

        return response()->noContent();
    }

    protected function storeVersion(UploadedFile $file, Document $document, Request $request): DocumentVersion
    {
        $next = ($document->versions()->max('version') ?? 0) + 1;
        $path = $file->storeAs(
            'documents/'.tenant()->getTenantKey().'/'.$document->id,
            'v'.$next.'-'.$file->getClientOriginalName(),
            'local'
        );

        $version = $document->versions()->create([
            'version' => $next,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()?->id,
        ]);

        $document->update(['current_version_id' => $version->id]);

        return $version;
    }
}
