<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Market\MarketProductResource;
use App\Models\PlatformSetting;
use App\Services\Ai\AiOrderAssistant;
use App\Services\Ai\AiProductSearch;
use App\Services\MarketplaceSearch;
use App\Support\GeoPoint;
use Illuminate\Http\Request;

/**
 * The AI features for the mobile app. Both follow the admin switches in
 * Admin > AI Assistant; the app hides each button while it is off.
 */
class AiController extends Controller
{
    /** Which AI buttons the app should show. Public. */
    public function config()
    {
        return response()->json([
            'search_enabled' => AiProductSearch::enabled(),
            'search_guests' => (bool) PlatformSetting::get(PlatformSetting::AI_SEARCH_GUESTS),
            'order_enabled' => AiOrderAssistant::enabled(),
        ]);
    }

    /**
     * Ask AI search. Public when the admin allows guests; a signed-in
     * customer's bearer token is honoured when sent.
     */
    public function search(Request $request, AiProductSearch $ai, MarketplaceSearch $search)
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:'.AiProductSearch::MAX_QUERY_LENGTH],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ] + $this->locationRules());

        abort_unless(AiProductSearch::enabled(), 403, 'Ask AI is switched off.');

        $user = auth('sanctum')->user();
        if (! AiProductSearch::availableTo($user)) {
            return response()->json(['status' => 'sign_in', 'message' => 'Sign in to use Ask AI.'], 401);
        }

        $result = $ai->search($data['query'], $user, $request->ip());
        $from = GeoPoint::tryFrom($request->input('lat'), $request->input('lng'));
        $fallback = in_array($result['status'], [AiProductSearch::STATUS_UNAVAILABLE, AiProductSearch::STATUS_LIMITED], true);

        $filters = $fallback
            ? ['any_words' => AiProductSearch::fallbackWords($data['query'])]
            : ['product_ids' => $result['product_ids'], 'in_stock' => $result['in_stock']];
        $sort = $fallback ? MarketplaceSearch::SORT_NEAREST : $result['sort'];
        if (! $from && $sort === MarketplaceSearch::SORT_NEAREST) {
            $sort = $fallback ? MarketplaceSearch::SORT_PRICE : MarketplaceSearch::SORT_RELEVANCE;
        }

        return MarketProductResource::collection($search->products($filters, $from, $sort, $data['per_page'] ?? 20))
            ->additional(['ai' => [
                'status' => $result['status'],
                'summary' => $result['summary'],
                'fallback' => $fallback,
            ]]);
    }

    /**
     * One turn of the order assistant. The app keeps the conversation and
     * sends it back each time; assistant turns should be sent with the
     * "memo" returned here appended, so the assistant remembers its basket.
     */
    public function order(Request $request, AiOrderAssistant $assistant)
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:30'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ] + $this->locationRules());

        abort_unless(AiOrderAssistant::enabled(), 403, 'The AI order assistant is switched off.');
        abort_unless(end($data['messages'])['role'] === 'user', 422, 'The last message must be from the customer.');

        $result = $assistant->reply(
            $request->user(),
            $data['messages'],
            GeoPoint::tryFrom($request->input('lat'), $request->input('lng')),
            $request->ip(),
        );

        return response()->json($result + ['memo' => AiOrderAssistant::basketMemo($result['basket'])]);
    }

    protected function locationRules(): array
    {
        return [
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ];
    }
}
