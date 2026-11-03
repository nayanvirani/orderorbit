<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SavedReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Saved replies used by "Suggest reply" (G35). */
class SavedReplyController extends Controller
{
    public function index(): View
    {
        return view('admin.replies', ['replies' => SavedReply::orderByDesc('use_count')->orderBy('title')->get(), 'edit' => null]);
    }

    public function edit(int $reply): View
    {
        return view('admin.replies', ['replies' => SavedReply::orderByDesc('use_count')->orderBy('title')->get(), 'edit' => SavedReply::findOrFail($reply)]);
    }

    public function save(Request $request, ?int $reply = null): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'keywords' => ['nullable', 'string', 'max:500'], 'body' => ['required', 'string', 'max:10000']]);
        $model = $reply ? SavedReply::findOrFail($reply) : new SavedReply;
        $model->fill($data)->save();
        AuditLog::record('admin.saved_reply_saved', null, ['reply' => $model->id, 'by' => $request->user()->email]);

        return redirect()->route('admin.replies')->with('status', 'Saved reply "'.$model->title.'" saved.');
    }

    public function delete(Request $request, int $reply): RedirectResponse
    {
        $model = SavedReply::findOrFail($reply);
        $model->delete();
        AuditLog::record('admin.saved_reply_deleted', null, ['title' => $model->title, 'by' => $request->user()->email]);

        return redirect()->route('admin.replies')->with('status', 'Saved reply deleted.');
    }

    /** A sent reply that started from a saved reply counts towards its ranking. */
    public static function used(?string $id): void
    {
        if ($id !== null && ctype_digit($id)) {
            SavedReply::whereKey((int) $id)->increment('use_count');
        }
    }
}
