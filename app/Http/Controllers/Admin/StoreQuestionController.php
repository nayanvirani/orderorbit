<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Questions\StoreQuestion;
use App\Services\Questions\QuestionLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Internal Admin › Store questions (G18): add questions to every store's library from the same
 * building blocks as the built-in ones (a measure, comparison, ranking or offers question and a
 * period), with your own wording. Turning one off hides it everywhere.
 */
class StoreQuestionController extends Controller
{
    public function index(QuestionLibrary $library): View
    {
        return view('admin.questions', [
            'questions' => StoreQuestion::orderByDesc('id')->get(), 'builtIn' => count($library->all()) - StoreQuestion::where('active', true)->count(),
            'topics' => $library->topics(), 'periods' => QuestionLibrary::PERIODS, 'measures' => QuestionLibrary::MEASURES,
            'rankings' => collect(QuestionLibrary::RANKINGS)->flatMap(fn ($r, $dim) => collect($r[2])->mapWithKeys(fn ($m, $key) => ["{$dim}:{$key}" => $m[0]]))->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'topic' => ['required', Rule::in(app(QuestionLibrary::class)->topics())],
            'text' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::in(['value', 'compare', 'rank', 'offers'])],
            'measure' => ['required_if:kind,value,compare', 'nullable', Rule::in(array_keys(QuestionLibrary::MEASURES))],
            'ranking' => ['required_if:kind,rank', 'nullable', 'string'],
            'offers' => ['required_if:kind,offers', 'nullable', Rule::in(['total', 'top'])],
            'period' => ['required', Rule::in(array_keys(QuestionLibrary::PERIODS))],
        ]);
        $spec = ['kind' => $data['kind'], 'period' => $data['period']];
        if (in_array($data['kind'], ['value', 'compare'], true)) {
            $spec['measure'] = $data['measure'];
        } elseif ($data['kind'] === 'offers') {
            $spec['mode'] = $data['offers'];
        } else {
            [$dim, $key] = explode(':', (string) $data['ranking']) + [1 => null];
            $r = QuestionLibrary::RANKINGS[$dim][2][$key] ?? null;
            abort_unless($r, 422);
            $spec += ['dim' => $dim, 'measure' => $r[1], 'dir' => $r[2]];
        }
        $q = StoreQuestion::create(['topic' => $data['topic'], 'text' => $data['text'], 'spec' => $spec, 'created_by' => $request->user()->email]);
        AuditLog::record('admin.store_question_added', null, ['id' => $q->id, 'text' => $q->text, 'by' => $request->user()->email]);

        return back()->with('status', 'Question added for every store.');
    }

    public function toggle(Request $request, StoreQuestion $question): RedirectResponse
    {
        $question->forceFill(['active' => ! $question->active])->save();

        return back()->with('status', $question->active ? 'Question shown again.' : 'Question hidden.');
    }
}
