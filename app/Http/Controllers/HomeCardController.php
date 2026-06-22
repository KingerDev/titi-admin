<?php

namespace App\Http\Controllers;

use App\Models\HomeCard;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeCardController extends Controller
{
    public function index()
    {
        $cards = HomeCard::orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn($c) => $this->present($c));

        return Inertia::render('HomeCards/Index', compact('cards'));
    }

    public function create()
    {
        return Inertia::render('HomeCards/Form', [
            'card' => null,
            'cards' => $this->allPresented(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCard($request);
        $data = $this->convertDatesToUtc($data);

        $data['sort_order'] = (int) HomeCard::max('sort_order') + 1;

        $card = HomeCard::create($data);

        return redirect()->route('home-cards.edit', $card->id)
            ->with('success', 'Karta bola uložená.');
    }

    public function edit(int $id)
    {
        $card = HomeCard::findOrFail($id);

        return Inertia::render('HomeCards/Form', [
            'card' => $this->present($card),
            'cards' => $this->allPresented(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $card = HomeCard::findOrFail($id);
        $data = $this->validateCard($request);
        $data = $this->convertDatesToUtc($data);

        $card->update($data);

        return back()->with('success', 'Karta bola uložená.');
    }

    public function destroy(int $id)
    {
        HomeCard::findOrFail($id)->delete();

        return redirect()->route('home-cards.index')
            ->with('success', 'Karta bola zmazaná.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ]);

        foreach ($data['ids'] as $index => $id) {
            HomeCard::where('id', $id)->update(['sort_order' => $index]);
        }

        return back()->with('success', 'Poradie bolo uložené.');
    }

    /**
     * Našepkávač kategórií pre formulár karty – hľadá podľa názvu (SK, language_id = 2),
     * vracia id, názov a celú cestu (rodič › … › kategória).
     */
    public function searchCategories(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        // Všetky aktívne kategórie + názvy (na zostavenie cesty z parent_id).
        $all = \Illuminate\Support\Facades\DB::connection('titi')
            ->table('titi_category as c')
            ->leftJoin('titi_category_description as cd', function ($j) {
                $j->on('c.category_id', '=', 'cd.category_id')->where('cd.language_id', 2);
            })
            ->where('c.status', 1)
            ->select('c.category_id', 'c.parent_id', 'cd.name')
            ->get();

        $byId = [];
        foreach ($all as $c) {
            $byId[$c->category_id] = $c;
        }

        $pathOf = function ($c) use ($byId) {
            $parts = [];
            $cur = $c;
            $guard = 0;
            while ($cur && $guard++ < 12) {
                if (!empty($cur->name)) {
                    array_unshift($parts, $cur->name);
                }
                $cur = ($cur->parent_id && isset($byId[$cur->parent_id])) ? $byId[$cur->parent_id] : null;
            }
            return implode(' › ', $parts);
        };

        $ql = mb_strtolower($q);
        $results = [];
        foreach ($all as $c) {
            if (!empty($c->name) && mb_strpos(mb_strtolower($c->name), $ql) !== false) {
                $results[] = [
                    'category_id' => (int) $c->category_id,
                    'name'        => $c->name,
                    'path'        => $pathOf($c),
                ];
            }
        }

        // Kratšie (relevantnejšie) názvy hore, max 25.
        usort($results, fn ($a, $b) => mb_strlen($a['name']) <=> mb_strlen($b['name']));

        return response()->json(array_slice($results, 0, 25));
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function allPresented()
    {
        return HomeCard::orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($c) => $this->present($c));
    }

    private function present(HomeCard $c): array
    {
        return [
            'id'                 => $c->id,
            'top_text'           => $c->top_text,
            'title'              => $c->title,
            'subtitle'           => $c->subtitle,
            'bg_color'           => $c->bg_color,
            'text_color'         => $c->text_color,
            'top_text_color'     => $c->top_text_color,
            'col_span'           => $c->col_span,
            'row_span'           => $c->row_span,
            'app_route'          => $c->app_route,
            'external_url'       => $c->external_url,
            'image_url'          => $c->image_url,
            'pattern'            => $c->pattern,
            'decor'              => $c->decor,
            'show_arrow'         => $c->show_arrow,
            'audience'           => $c->audience,
            'platform'           => $c->platform,
            'loyalty_visibility' => $c->loyalty_visibility,
            'sort_order'         => $c->sort_order,
            'active'             => $c->active,
            'valid_from'         => $c->valid_from?->setTimezone('Europe/Bratislava')->format('Y-m-d\TH:i'),
            'valid_to'           => $c->valid_to?->setTimezone('Europe/Bratislava')->format('Y-m-d\TH:i'),
        ];
    }

    private function convertDatesToUtc(array $data): array
    {
        foreach (['valid_from', 'valid_to'] as $field) {
            if (!empty($data[$field])) {
                $data[$field] = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $data[$field], 'Europe/Bratislava')->utc();
            } else {
                $data[$field] = null;
            }
        }
        return $data;
    }

    private function validateCard(Request $request): array
    {
        // Pozn.: regex obsahuje '|', preto musí byť pravidlo pole (nie pipe-string).
        $hex = 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/';

        return $request->validate([
            'top_text'           => 'nullable|string|max:60',
            'title'              => 'required|string|max:150',
            'subtitle'           => 'nullable|string|max:150',
            'bg_color'           => ['required', 'string', 'max:9', $hex],
            'text_color'         => ['required', 'string', 'max:9', $hex],
            'top_text_color'     => ['nullable', 'string', 'max:9', $hex],
            'col_span'           => 'required|integer|min:1|max:12',
            'row_span'           => 'required|integer|min:1|max:6',
            'app_route'          => 'nullable|string|max:255',
            'external_url'       => 'nullable|string|max:500',
            'image_url'          => 'nullable|string|max:500',
            'pattern'            => 'required|in:none,dots,grid,diagonal,cross,zigzag,wave',
            'decor'              => 'required|in:none,bubbles,blob,rings,corner,confetti,waves',
            'show_arrow'         => 'boolean',
            'audience'           => 'required|in:all,guest,auth',
            'platform'           => 'required|in:all,ios,android',
            'loyalty_visibility' => 'required|in:any,on,off',
            'active'             => 'boolean',
            'valid_from'         => 'nullable|date',
            'valid_to'           => 'nullable|date',
        ]);
    }
}
