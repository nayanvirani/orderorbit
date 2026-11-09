<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Questions\QuestionAnswerer;
use App\Services\Questions\QuestionLibrary;
use App\Support\Features;
use App\Support\Plans;
use App\Support\Spa\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Insights › Store questions (G18): the library with topics and search, one-click answers,
 * favourites and recently asked. Free answers 10 questions a month; Starter and up, all.
 */
class StoreQuestionController extends Controller
{
    public function __construct(private readonly QuestionLibrary $library) {}

    public function index(Request $request, Store $store): Page
    {
        abort_unless(Features::enabled('store_questions', $store) && Features::enabled('profit', $store), 404);
        $user = $request->attributes->get('storeUser');

        return page('insights/questions', [
            'topics' => $this->library->topics(),
            'questions' => array_values(array_map(fn ($q) => [$q['id'], $q['topic'], $q['text']], $this->library->all())),
            'starters' => QuestionLibrary::STARTERS,
            'favourites' => $user ? DB::table('store_question_favourites')->where('store_user_id', $user->id)->orderByDesc('id')->pluck('question')->all() : [],
            'recent' => $user ? DB::table('store_question_asks')->where('store_user_id', $user->id)->orderByDesc('id')->limit(40)->pluck('question')->unique()->take(8)->values()->all() : [],
            'remaining' => $this->remaining($store),
            'limit' => $store->planLimit('store_questions'),
            'upgrade' => $store->planLimit('store_questions') === null ? null : Plans::unlockMessage('sales_insights', 'ask every question', $store),
        ]);
    }

    /** One answer, from the store's data at this moment. */
    public function ask(Request $request, Store $store, QuestionAnswerer $answers): JsonResponse
    {
        abort_unless(Features::enabled('store_questions', $store) && Features::enabled('profit', $store), 404);
        $question = $this->library->find((string) $request->input('id')) ?? abort(404);
        $remaining = $this->remaining($store);
        if ($remaining !== null && $remaining <= 0) {
            return response()->json(['message' => 'Your plan\'s questions for this month are used up. They reset on the 1st, or upgrade to ask every question.', 'remaining' => 0], 402);
        }
        $started = microtime(true);
        $answer = $answers->answer($store, $question);
        DB::table('store_question_asks')->insert(['store_id' => $store->id, 'store_user_id' => $request->attributes->get('storeUser')?->id, 'question' => $question['id'], 'asked_at' => now()]);

        return response()->json(['question' => $question['text'], 'answer' => $answer, 'remaining' => $this->remaining($store), 'ms' => (int) round((microtime(true) - $started) * 1000)]);
    }

    public function favourite(Request $request, Store $store): JsonResponse
    {
        $user = $request->attributes->get('storeUser') ?? abort(403);
        $id = (string) $request->input('id');
        abort_unless($this->library->find($id), 404);
        if ($request->boolean('on')) {
            DB::table('store_question_favourites')->insertOrIgnore(['store_user_id' => $user->id, 'question' => $id, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('store_question_favourites')->where('store_user_id', $user->id)->where('question', $id)->delete();
        }

        return response()->json(['ok' => true]);
    }

    /** Answers left this month (null = no limit). */
    private function remaining(Store $store): ?int
    {
        $limit = $store->planLimit('store_questions');
        if ($limit === null) {
            return null;
        }
        $used = DB::table('store_question_asks')->where('store_id', $store->id)->where('asked_at', '>=', now()->startOfMonth())->count();

        return max(0, $limit - $used);
    }
}
