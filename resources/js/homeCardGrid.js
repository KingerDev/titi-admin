// Grid matematika 1:1 s aplikáciou (components/home/HomeCardsGrid.tsx).
// 12-stĺpcový grid, gap 12, padding kontajnera 16, pevná 1 riadková jednotka = 48 dp.
export const COLS = 12;
export const GAP = 12;
export const H_PADDING = 16;
export const ROW_UNIT_DP = 48;

const clampSpan = (s) => Math.min(Math.max(parseInt(s, 10) || 1, 1), COLS);
const clampRows = (r) => Math.max(parseInt(r, 10) || 1, 1);

/**
 * Metriky pre danú šírku „telefónu" (px). Vracia funkcie cardWidth/cardHeight,
 * ktoré dávajú presne tie isté rozmery ako appka pri rovnakej šírke obrazovky.
 */
export function gridMetrics(phoneWidth) {
    const containerWidth = phoneWidth - H_PADDING * 2;
    const unit = (containerWidth - GAP * (COLS - 1)) / COLS;

    return {
        containerWidth,
        unit,
        gap: GAP,
        cardWidth: (span) => {
            const s = clampSpan(span);
            return Math.floor(unit * s + GAP * (s - 1));
        },
        cardHeight: (rows) => {
            const r = clampRows(rows);
            return ROW_UNIT_DP * r + GAP * (r - 1);
        },
    };
}

// Minimálna výška podľa obsahu (1:1 s appkou) — aby sa pri row_span 1 zmestil titulok.
const CARD_PADDING = 16;
const TOP_TEXT_H = 15;
const TITLE_MT = 6;
const TITLE_LINE = 23;
const SUBTITLE_MT = 4;
const SUBTITLE_LINE = 16;

function contentMinHeight(card, titleLines, subtitleLines) {
    return CARD_PADDING * 2
        + (card.top_text ? TOP_TEXT_H + TITLE_MT : 0)
        + TITLE_LINE * Math.max(titleLines, 1)
        + (card.subtitle ? SUBTITLE_MT + SUBTITLE_LINE * Math.max(subtitleLines, 1) : 0);
}

/**
 * Reálna výška karty = max(výška z row_span, minimum podľa obsahu).
 * Počet riadkov titulku odhadneme z explicitných zalomení (\n) – appka meria presne,
 * tu stačí aproximácia pre náhľad.
 */
export function cardRenderHeight(card, metrics) {
    const titleLines = (card.title || '').includes('\n') ? 2 : 1;
    return Math.max(metrics.cardHeight(card.row_span), contentMinHeight(card, titleLines, 1));
}

/**
 * Skyline (masonry) packing 1:1 s aplikáciou – kartu položí na najľavejšiu pozíciu
 * s najmenším vrchom, takže nízke karty zapadnú do dier. Vracia { placements, height }.
 */
export function packCards(cards, metrics) {
    const step = metrics.unit + GAP;
    const colTop = new Array(COLS).fill(0);
    let height = 0;

    const placements = cards.map((card) => {
        const span = clampSpan(card.col_span);
        const h = cardRenderHeight(card, metrics);

        let bestX = 0;
        let bestY = Infinity;
        for (let x = 0; x <= COLS - span; x++) {
            let y = 0;
            for (let c = x; c < x + span; c++) y = Math.max(y, colTop[c]);
            if (y < bestY) { bestY = y; bestX = x; }
        }
        for (let c = bestX; c < bestX + span; c++) colTop[c] = bestY + h + GAP;
        height = Math.max(height, bestY + h);

        return { card, left: bestX * step, top: bestY, width: metrics.cardWidth(span), height: h };
    });

    return { placements, height };
}
