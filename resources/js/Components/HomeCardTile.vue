<script setup>
/**
 * Vykresľuje jednu domovskú kartu PRESNE ako v aplikácii
 * (components/home/HomeCardsGrid.tsx). Rozmery (width/height v px) sa
 * počítajú z grid matematiky appky a posielajú sem ako props.
 */
import { computed } from 'vue';

const props = defineProps({
    card:   { type: Object, required: true },
    width:  { type: Number, required: true },
    height: { type: Number, required: true },
});

const ARROW_MIN_HEIGHT = 100;
const ARROW_SIZE = 46;

const topColor = computed(() => props.card.top_text_color || props.card.text_color);
const pattern = computed(() => props.card.pattern ?? 'dots');
const decor = computed(() => props.card.decor ?? 'bubbles');
const showArrow = computed(() => props.height >= ARROW_MIN_HEIGHT && props.card.show_arrow !== false);

// Unikátne id patternu, aby sa <pattern> elementy na stránke (viac kariet) nekrížili.
const uid = Math.random().toString(36).slice(2);
const pid = computed(() => `pat-${uid}`);

// Tvary ozdôb – identické súradnice ako v appke (components/home/HomeCardsGrid.tsx).
function decorShapeList(d, W, H) {
    const M = Math.max(W, H);
    switch (d) {
        case 'bubbles':
            return [
                { kind: 'circle', cx: W * 0.88, cy: H, r: M * 0.42, o: 0.14 },
                { kind: 'circle', cx: W * 0.96, cy: 0, r: M * 0.22, o: 0.1 },
            ];
        case 'blob':
            return [
                { kind: 'circle', cx: W * 0.82, cy: H * 0.82, r: M * 0.5, o: 0.13 },
                { kind: 'circle', cx: W * 1.02, cy: H * 0.45, r: M * 0.28, o: 0.1 },
            ];
        case 'rings':
            return [
                { kind: 'ring', cx: W, cy: 0, r: M * 0.32, o: 0.18, sw: Math.max(2, M * 0.02) },
                { kind: 'ring', cx: W, cy: 0, r: M * 0.5, o: 0.14, sw: Math.max(2, M * 0.02) },
                { kind: 'ring', cx: W, cy: 0, r: M * 0.68, o: 0.1, sw: Math.max(2, M * 0.02) },
            ];
        case 'corner':
            return [{ kind: 'circle', cx: W, cy: 0, r: M * 0.62, o: 0.12 }];
        case 'confetti':
            return [
                { kind: 'circle', cx: W * 0.2, cy: H * 0.3, r: M * 0.05, o: 0.16 },
                { kind: 'circle', cx: W * 0.72, cy: H * 0.22, r: M * 0.035, o: 0.14 },
                { kind: 'circle', cx: W * 0.55, cy: H * 0.7, r: M * 0.045, o: 0.16 },
                { kind: 'ring', cx: W * 0.35, cy: H * 0.78, r: M * 0.045, o: 0.18, sw: 2 },
                { kind: 'ring', cx: W * 0.88, cy: H * 0.34, r: M * 0.05, o: 0.14, sw: 2 },
            ];
        case 'waves':
            return [
                { kind: 'path', d: `M0 ${H * 0.72} Q ${W * 0.25} ${H * 0.62} ${W * 0.5} ${H * 0.72} Q ${W * 0.75} ${H * 0.82} ${W} ${H * 0.72} L ${W} ${H} L 0 ${H} Z`, o: 0.1 },
                { kind: 'path', d: `M0 ${H * 0.83} Q ${W * 0.3} ${H * 0.73} ${W * 0.6} ${H * 0.83} Q ${W * 0.8} ${H * 0.89} ${W} ${H * 0.81} L ${W} ${H} L 0 ${H} Z`, o: 0.08 },
            ];
        default:
            return [];
    }
}
const decorShapes = computed(() => decorShapeList(decor.value, props.width, props.height));
const hasOverlay = computed(() => pattern.value !== 'none' || decor.value !== 'none');
</script>

<template>
    <div
        :style="{
            width: width + 'px',
            height: height + 'px',
            backgroundColor: card.bg_color,
            borderRadius: '24px',
            padding: '16px',
            boxShadow: '0 8px 16px rgba(27,34,51,0.10)',
            fontFamily: '-apple-system, BlinkMacSystemFont, \'SF Pro Display\', \'SF Pro Text\', system-ui, sans-serif',
        }"
        class="relative overflow-hidden flex flex-col justify-center"
    >
        <!-- Pattern + ozdobné tvary -->
        <svg v-if="hasOverlay" class="pointer-events-none absolute inset-0 h-full w-full">
            <defs v-if="pattern !== 'none'">
                <pattern v-if="pattern === 'dots'" :id="pid" patternUnits="userSpaceOnUse" :width="16" :height="16">
                    <circle :cx="2" :cy="2" :r="1.6" :fill="card.text_color" fill-opacity="0.09"/>
                </pattern>
                <pattern v-else-if="pattern === 'grid'" :id="pid" patternUnits="userSpaceOnUse" :width="22" :height="22">
                    <path d="M22 0 H0 V22" :stroke="card.text_color" stroke-opacity="0.1" stroke-width="1" fill="none"/>
                </pattern>
                <pattern v-else-if="pattern === 'diagonal'" :id="pid" patternUnits="userSpaceOnUse" :width="10" :height="10">
                    <path d="M-1 1 L1 -1 M0 10 L10 0 M9 11 L11 9" :stroke="card.text_color" stroke-opacity="0.1" stroke-width="1" fill="none"/>
                </pattern>
                <pattern v-else-if="pattern === 'cross'" :id="pid" patternUnits="userSpaceOnUse" :width="20" :height="20">
                    <path d="M10 6 V14 M6 10 H14" :stroke="card.text_color" stroke-opacity="0.1" stroke-width="1" fill="none"/>
                </pattern>
                <pattern v-else-if="pattern === 'zigzag'" :id="pid" patternUnits="userSpaceOnUse" :width="20" :height="10">
                    <path d="M0 8 L5 3 L10 8 L15 3 L20 8" :stroke="card.text_color" stroke-opacity="0.1" stroke-width="1" fill="none"/>
                </pattern>
                <pattern v-else-if="pattern === 'wave'" :id="pid" patternUnits="userSpaceOnUse" :width="20" :height="10">
                    <path d="M0 5 Q5 0 10 5 T20 5" :stroke="card.text_color" stroke-opacity="0.1" stroke-width="1" fill="none"/>
                </pattern>
            </defs>
            <rect v-if="pattern !== 'none'" width="100%" height="100%" :fill="`url(#${pid})`"/>

            <template v-for="(s, i) in decorShapes" :key="i">
                <path v-if="s.kind === 'path'" :d="s.d" fill="#fff" :fill-opacity="s.o"/>
                <circle v-else-if="s.kind === 'ring'" :cx="s.cx" :cy="s.cy" :r="s.r" fill="none" stroke="#fff" :stroke-opacity="s.o" :stroke-width="s.sw"/>
                <circle v-else :cx="s.cx" :cy="s.cy" :r="s.r" fill="#fff" :fill-opacity="s.o"/>
            </template>
        </svg>

        <img
            v-if="card.image_url"
            :src="card.image_url"
            style="position:absolute; right:-8px; bottom:-8px; width:110px; height:110px; opacity:0.25; object-fit:contain;"
            alt=""
        />

        <span
            v-if="card.top_text"
            :style="{ color: topColor, fontSize: '11px', letterSpacing: '1.5px' }"
            class="font-extrabold uppercase truncate relative"
        >
            {{ card.top_text }}
        </span>

        <span
            :style="{ color: card.text_color, fontSize: '19px', lineHeight: '23px' }"
            class="font-extrabold relative home-card-clamp"
            :class="card.top_text ? 'mt-1.5' : ''"
        >{{ card.title }}</span>

        <span
            v-if="card.subtitle"
            :style="{ color: card.text_color, fontSize: '12.5px', lineHeight: '16px', opacity: 0.8 }"
            class="relative mt-1 home-card-clamp"
        >{{ card.subtitle }}</span>

        <!-- Šípka -->
        <div
            v-if="showArrow"
            :style="{
                position:'absolute', right:'16px', bottom:'16px', width: ARROW_SIZE + 'px', height: ARROW_SIZE + 'px',
                borderRadius:'9999px', backgroundColor: card.text_color,
            }"
            class="flex items-center justify-center"
        >
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" :stroke="card.bg_color" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
        </div>
    </div>
</template>

<style scoped>
/* title rešpektuje \n (ako numberOfLines={2} + pre-line v appke) a max 2 riadky */
.home-card-clamp {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    white-space: pre-line;
    word-break: break-word;
}
</style>
