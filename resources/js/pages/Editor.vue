<script setup lang="ts">
import type { ProductTag } from '@/lib/laria';
import { apiFetch } from '@/lib/laria';
import LariaLayout from '@/layouts/LariaLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Brush,
    Camera,
    Eraser,
    Link2,
    Loader2,
    Search,
    Sparkles,
    Undo2,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type Point = { x: number; y: number }; // normalizado (0..1) relativo à área da imagem
type Stroke = Point[];

const step = ref<'pick' | 'draw' | 'result'>('pick');
const error = ref<string | null>(null);

// --- Foto escolhida ---
const fileInput = ref<HTMLInputElement | null>(null);
const originalFile = ref<File | null>(null);
const imageUrl = ref<string | null>(null); // object URL local
const imageEl = ref<HTMLImageElement | null>(null);

function pickFile() {
    fileInput.value?.click();
}

function onFileChosen(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    originalFile.value = file;
    if (imageUrl.value) URL.revokeObjectURL(imageUrl.value);
    imageUrl.value = URL.createObjectURL(file);
    strokes.value = [];
    step.value = 'draw';
    requestAnimationFrame(resizeCanvas);
}

// --- Desenho sobre a foto ---
const BRUSH_COLOR = 'rgba(233, 30, 99, 0.8)'; // rosa translúcido, bem visível
const BRUSH_WIDTH = 26; // px na resolução de exibição

const surface = ref<HTMLDivElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
const strokes = ref<Stroke[]>([]);
let live: Stroke | null = null;
let drawing = false;

const canDraw = computed(() => step.value === 'draw');

function surfaceSize() {
    const el = surface.value;
    return el ? { w: el.clientWidth, h: el.clientHeight } : { w: 0, h: 0 };
}

function resizeCanvas() {
    const el = canvas.value;
    if (!el) return;
    const { w, h } = surfaceSize();
    const dpr = window.devicePixelRatio || 1;
    el.width = Math.round(w * dpr);
    el.height = Math.round(h * dpr);
    redraw();
}

function drawStrokes(ctx: CanvasRenderingContext2D, w: number, h: number, scale: number) {
    ctx.strokeStyle = BRUSH_COLOR;
    ctx.fillStyle = BRUSH_COLOR;
    ctx.lineWidth = BRUSH_WIDTH * scale;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    for (const stroke of [...strokes.value, ...(live ? [live] : [])]) {
        if (!stroke.length) continue;
        if (stroke.length === 1) {
            ctx.beginPath();
            ctx.arc(stroke[0].x * w, stroke[0].y * h, (BRUSH_WIDTH * scale) / 2, 0, Math.PI * 2);
            ctx.fill();
            continue;
        }
        ctx.beginPath();
        ctx.moveTo(stroke[0].x * w, stroke[0].y * h);
        for (let i = 1; i < stroke.length; i++) {
            ctx.lineTo(stroke[i].x * w, stroke[i].y * h);
        }
        ctx.stroke();
    }
}

function redraw() {
    const el = canvas.value;
    const ctx = el?.getContext('2d');
    if (!el || !ctx) return;
    const dpr = window.devicePixelRatio || 1;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const { w, h } = surfaceSize();
    ctx.clearRect(0, 0, w, h);
    drawStrokes(ctx, w, h, 1);
}

function pointFrom(event: PointerEvent): Point {
    const rect = canvas.value!.getBoundingClientRect();
    return {
        x: Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)),
        y: Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height)),
    };
}

function onPointerDown(event: PointerEvent) {
    if (!canDraw.value) return;
    drawing = true;
    canvas.value?.setPointerCapture(event.pointerId);
    live = [pointFrom(event)];
    redraw();
}

function onPointerMove(event: PointerEvent) {
    if (!drawing || !live) return;
    live.push(pointFrom(event));
    redraw();
}

function onPointerUp() {
    if (!drawing) return;
    drawing = false;
    if (live && live.length) strokes.value = [...strokes.value, live];
    live = null;
    redraw();
}

function undo() {
    strokes.value = strokes.value.slice(0, -1);
    redraw();
}

function clearStrokes() {
    strokes.value = [];
    redraw();
}

/** Centro normalizado (0..1) da região marcada — vira a posição do hotspot. */
function normalizedCenter(): { x: number; y: number } {
    let minX = Infinity, minY = Infinity, maxX = 0, maxY = 0;
    let any = false;
    for (const stroke of strokes.value) {
        for (const p of stroke) {
            any = true;
            minX = Math.min(minX, p.x);
            minY = Math.min(minY, p.y);
            maxX = Math.max(maxX, p.x);
            maxY = Math.max(maxY, p.y);
        }
    }
    return any ? { x: (minX + maxX) / 2, y: (minY + maxY) / 2 } : { x: 0.5, y: 0.5 };
}

/** Exporta a imagem anotada (foto + traços, como exibida) em PNG. */
function exportAnnotated(): Promise<Blob> {
    return new Promise((resolve, reject) => {
        const img = imageEl.value;
        const { w, h } = surfaceSize();
        if (!img || !w || !h) return reject(new Error('Imagem indisponível'));

        const scale = 2; // equivale ao pixelRatio 2.0 do app
        const out = document.createElement('canvas');
        out.width = w * scale;
        out.height = h * scale;
        const ctx = out.getContext('2d')!;
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, out.width, out.height);

        // Reproduz o object-contain do <img> exibido.
        const ratio = Math.min(w / img.naturalWidth, h / img.naturalHeight);
        const dw = img.naturalWidth * ratio * scale;
        const dh = img.naturalHeight * ratio * scale;
        ctx.drawImage(img, (out.width - dw) / 2, (out.height - dh) / 2, dw, dh);

        drawStrokes(ctx, out.width, out.height, scale);
        out.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Falha ao exportar'))), 'image/png');
    });
}

onMounted(() => window.addEventListener('resize', resizeCanvas));
onBeforeUnmount(() => {
    window.removeEventListener('resize', resizeCanvas);
    if (imageUrl.value) URL.revokeObjectURL(imageUrl.value);
});

// --- Produto (scraping) ---
const productUrl = ref('');
const product = ref<ProductTag | null>(null);
const scraping = ref(false);

async function scrape() {
    const url = productUrl.value.trim();
    if (!url || scraping.value) return;
    scraping.value = true;
    error.value = null;
    try {
        const res = await apiFetch<Omit<ProductTag, 'posX' | 'posY'>>('/scrape', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url }),
        });
        product.value = { ...res, posX: 0.5, posY: 0.5 };
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        scraping.value = false;
    }
}

// --- Geração ---
const generating = ref(false);
const generatedUrl = ref<string | null>(null);
const originalUploadedUrl = ref<string | null>(null);

async function generate() {
    if (!strokes.value.length) {
        error.value = 'Desenhe sobre o elemento que deseja trocar';
        return;
    }
    if (!product.value?.imageUrl) {
        error.value = 'Vincule um produto pela URL';
        return;
    }
    if (!originalFile.value) return;

    generating.value = true;
    error.value = null;
    try {
        const annotated = await exportAnnotated();
        const center = normalizedCenter();

        const form = new FormData();
        form.append('original', originalFile.value);
        form.append('annotated', annotated, 'annotated.png');
        form.append('productImageUrl', product.value.imageUrl);
        if (product.value.title) form.append('productTitle', product.value.title);

        const res = await apiFetch<{ imageUrl: string; originalImageUrl: string }>('/generate', {
            method: 'POST',
            body: form,
        });

        product.value = { ...product.value, posX: center.x, posY: center.y };
        generatedUrl.value = res.imageUrl;
        originalUploadedUrl.value = res.originalImageUrl;
        step.value = 'result';
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        generating.value = false;
    }
}

// --- Resultado e publicação ---
const showOriginal = ref(false);
const caption = ref('');
const publishing = ref(false);

function publish() {
    if (!generatedUrl.value || !product.value) return;
    publishing.value = true;
    router.post(
        '/posts',
        {
            imageUrl: generatedUrl.value,
            originalImageUrl: originalUploadedUrl.value,
            caption: caption.value.trim() || null,
            products: [product.value],
        },
        { onFinish: () => (publishing.value = false) },
    );
}

function startOver() {
    step.value = 'pick';
    strokes.value = [];
    product.value = null;
    productUrl.value = '';
    generatedUrl.value = null;
    caption.value = '';
    error.value = null;
}
</script>

<template>
    <Head title="Criar" />
    <LariaLayout>
        <!-- capture="environment": no celular abre a câmera traseira direto, sem galeria -->
        <input
            ref="fileInput"
            type="file"
            accept="image/*"
            capture="environment"
            class="hidden"
            @change="onFileChosen"
        />

        <!-- Passo 1: escolher foto -->
        <div v-if="step === 'pick'" class="flex flex-col items-center gap-4 px-6 pt-20 text-center">
            <span class="rounded-full bg-lavender/20 p-6">
                <Camera class="h-12 w-12 text-lavender-deep" />
            </span>
            <h2 class="text-xl font-bold">Nova criação</h2>
            <p class="max-w-sm text-black/55">
                Tire uma foto, pinte o elemento que quer trocar e vincule um produto da internet.
                A IA gera a nova imagem trocando apenas o que você marcou.
            </p>
            <button
                type="button"
                class="mt-2 flex items-center gap-2 rounded-2xl bg-lavender px-8 py-3.5 font-semibold text-white shadow-sm transition hover:bg-lavender-deep"
                @click="pickFile"
            >
                <Camera class="h-5 w-5" />
                Tirar foto
            </button>
        </div>

        <!-- Passo 2: desenhar e vincular produto -->
        <div v-else-if="step === 'draw'" class="flex flex-col">
            <div class="flex items-center justify-between px-4 py-2">
                <h2 class="font-bold">Marcar e gerar</h2>
                <div class="flex gap-1">
                    <button
                        type="button"
                        class="rounded-full p-2 text-black/60 transition hover:bg-lavender-tint disabled:opacity-30"
                        :disabled="!strokes.length"
                        title="Desfazer"
                        @click="undo"
                    >
                        <Undo2 class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="rounded-full p-2 text-black/60 transition hover:bg-lavender-tint disabled:opacity-30"
                        :disabled="!strokes.length"
                        title="Limpar"
                        @click="clearStrokes"
                    >
                        <Eraser class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        class="rounded-full p-2 text-black/60 transition hover:bg-lavender-tint"
                        title="Tirar outra foto"
                        @click="pickFile"
                    >
                        <Camera class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <!-- Superfície de desenho -->
            <div ref="surface" class="relative aspect-square w-full overflow-hidden bg-black sm:rounded-2xl">
                <img
                    ref="imageEl"
                    :src="imageUrl ?? ''"
                    alt="Foto escolhida"
                    class="pointer-events-none absolute inset-0 h-full w-full select-none object-contain"
                    draggable="false"
                    @load="resizeCanvas"
                />
                <canvas
                    ref="canvas"
                    class="absolute inset-0 h-full w-full cursor-crosshair"
                    style="touch-action: none"
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerUp"
                    @pointercancel="onPointerUp"
                />
            </div>

            <!-- Painel inferior -->
            <div class="mx-3 -mt-3 rounded-2xl bg-white p-4 shadow-sm sm:mx-0 sm:mt-3">
                <p class="mb-3 flex items-center gap-2 text-[13px] text-black/55">
                    <Brush class="h-4 w-4 shrink-0 text-lavender-deep" />
                    Pinte o elemento que quer trocar e cole o link do produto.
                </p>

                <!-- URL do produto / card do produto -->
                <div v-if="!product" class="flex gap-2">
                    <div class="relative flex-1">
                        <Link2 class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-black/35" />
                        <input
                            v-model="productUrl"
                            type="url"
                            inputmode="url"
                            placeholder="https://loja.com/produto"
                            class="w-full rounded-2xl border-0 bg-lavender-tint py-3 pl-10 pr-3 text-[15px] outline-none ring-lavender transition focus:ring-2"
                            @keydown.enter.prevent="scrape"
                        />
                    </div>
                    <button
                        type="button"
                        class="flex w-12 items-center justify-center rounded-2xl bg-lavender text-white transition hover:bg-lavender-deep disabled:opacity-50"
                        :disabled="scraping || !productUrl.trim()"
                        title="Buscar produto"
                        @click="scrape"
                    >
                        <Loader2 v-if="scraping" class="h-5 w-5 animate-spin" />
                        <Search v-else class="h-5 w-5" />
                    </button>
                </div>
                <div v-else class="flex items-center gap-3 rounded-2xl bg-lavender-tint p-2.5">
                    <img
                        v-if="product.imageUrl"
                        :src="product.imageUrl"
                        alt=""
                        class="h-[52px] w-[52px] rounded-xl object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="line-clamp-2 text-sm font-semibold">{{ product.title ?? 'Produto' }}</p>
                        <p v-if="product.price" class="text-sm text-lavender-deep">{{ product.price }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-2 text-black/50 hover:bg-white"
                        title="Remover produto"
                        @click="product = null"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <p v-if="error" class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ error }}
                </p>

                <button
                    type="button"
                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-2xl bg-lavender py-3.5 font-semibold text-white transition hover:bg-lavender-deep disabled:opacity-60"
                    :disabled="generating"
                    @click="generate"
                >
                    <Loader2 v-if="generating" class="h-5 w-5 animate-spin" />
                    <Sparkles v-else class="h-5 w-5" />
                    {{ generating ? 'Gerando com IA…' : 'Gerar' }}
                </button>
            </div>
        </div>

        <!-- Passo 3: resultado -->
        <div v-else class="flex flex-col">
            <div class="relative aspect-square w-full overflow-hidden bg-black sm:mt-3 sm:rounded-2xl">
                <img
                    :src="showOriginal ? (originalUploadedUrl ?? '') : (generatedUrl ?? '')"
                    alt="Resultado"
                    class="absolute inset-0 h-full w-full object-contain"
                />
                <span class="absolute right-3 top-3 flex items-center gap-1.5 rounded-full bg-black/55 px-3 py-1.5 text-xs text-white">
                    <Sparkles class="h-3.5 w-3.5" />
                    {{ showOriginal ? 'Original' : 'Gerada por IA' }}
                </span>
            </div>

            <!-- Antes / depois -->
            <div class="mx-auto mt-4 flex rounded-2xl bg-lavender-tint p-1">
                <button
                    type="button"
                    class="rounded-xl px-6 py-2 text-sm font-semibold transition"
                    :class="!showOriginal ? 'bg-white text-lavender-deep shadow-sm' : 'text-black/50'"
                    @click="showOriginal = false"
                >
                    Depois
                </button>
                <button
                    type="button"
                    class="rounded-xl px-6 py-2 text-sm font-semibold transition"
                    :class="showOriginal ? 'bg-white text-lavender-deep shadow-sm' : 'text-black/50'"
                    @click="showOriginal = true"
                >
                    Antes
                </button>
            </div>

            <div class="mx-3 mt-4 flex flex-col gap-4 rounded-2xl bg-white p-4 shadow-sm sm:mx-0">
                <div v-if="product" class="flex items-center gap-3 rounded-2xl bg-lavender-tint px-3 py-2.5">
                    <span class="min-w-0 flex-1 truncate text-sm">{{ product.title ?? product.sourceUrl }}</span>
                    <span v-if="product.price" class="text-sm font-bold text-lavender-deep">{{ product.price }}</span>
                </div>
                <textarea
                    v-model="caption"
                    rows="3"
                    placeholder="Conte algo sobre essa imagem…"
                    class="w-full resize-none rounded-2xl border-0 bg-lavender-tint p-4 text-[15px] outline-none ring-lavender transition focus:ring-2"
                />
                <button
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-lavender py-3.5 font-semibold text-white transition hover:bg-lavender-deep disabled:opacity-60"
                    :disabled="publishing"
                    @click="publish"
                >
                    <Loader2 v-if="publishing" class="h-5 w-5 animate-spin" />
                    {{ publishing ? 'Publicando…' : 'Publicar no perfil' }}
                </button>
                <button type="button" class="text-sm font-semibold text-black/50 hover:text-black/70" @click="startOver">
                    Começar de novo
                </button>
            </div>
        </div>
    </LariaLayout>
</template>
