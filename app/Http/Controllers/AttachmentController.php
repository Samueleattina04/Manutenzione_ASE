<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /** Serve un'immagine allegata (solo utenti autenticati). */
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        $headers = ['Cache-Control' => 'private, max-age=86400'];
        if ($attachment->mime_type) {
            $headers['Content-Type'] = $attachment->mime_type;
        }

        $name = $attachment->original_name ?: basename($attachment->path);

        return $request->boolean('download')
            ? Storage::disk('local')->download($attachment->path, $name, $headers)
            : Storage::disk('local')->response($attachment->path, $name, $headers);
    }
}
