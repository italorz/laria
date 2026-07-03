<script setup lang="ts">
import UserAvatar from '@/components/laria/UserAvatar.vue';
import type { Post, ProductTag } from '@/lib/laria';
import { apiFetch, timeAgo } from '@/lib/laria';
import LariaLayout from '@/layouts/LariaLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, ExternalLink, Heart, ShoppingBag, Tag, X } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{ post: Post }>();

const post = ref<Post>({ ...props.post });
const showTags = ref(true);
const selectedProduct = ref<ProductTag | null>(null);

// Like otimista com reversão em caso de erro.
async function toggleLike() {
    const previous = { liked: post.value.liked, likeCount: post.value.likeCount };
    post.value = {
        ...post.value,
        liked: !post.value.liked,
        likeCount: post.value.likeCount + (post.value.liked ? -1 : 1),
    };
    try {
        const res = await apiFetch<{ liked: boolean; likeCount: number }>(
            `/posts/${post.value.id}/like`,
            { method: 'POST' },
        );
        post.value = { ...post.value, ...res };
    } catch {
        post.value = { ...post.value, ...previous };
    }
}
</script>

<template>
    <Head title="Publicação" />
    <LariaLayout>
        <article class="sm:mt-3">
            <!-- Autor -->
            <div class="flex items-center justify-between px-4 py-2.5 sm:px-0">
                <Link :href="`/perfil/${post.author.id}`" class="flex items-center gap-3">
                    <UserAvatar :src="post.author.avatarUrl" :alt="post.author.displayName" />
                    <span>
                        <span class="block font-bold">{{ post.author.displayName }}</span>
                        <span class="block text-xs text-black/45">{{ timeAgo(post.createdAt) }}</span>
                    </span>
                </Link>
                <button
                    v-if="post.products.length"
                    type="button"
                    class="rounded-full p-2 transition hover:bg-lavender-tint"
                    :class="showTags ? 'text-lavender-deep' : 'text-black/40'"
                    :title="showTags ? 'Ocultar produtos' : 'Mostrar produtos'"
                    @click="showTags = !showTags"
                >
                    <Tag class="h-5 w-5" />
                </button>
            </div>

            <!-- Imagem com hotspots -->
            <div class="relative aspect-square w-full overflow-hidden bg-black sm:rounded-2xl">
                <img :src="post.imageUrl" alt="" class="absolute inset-0 h-full w-full object-contain" />
                <template v-if="showTags">
                    <button
                        v-for="product in post.products"
                        :key="product.id"
                        type="button"
                        class="absolute flex h-9 w-9 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-2 border-lavender-deep bg-white/90 shadow-md transition hover:scale-110"
                        :style="{ left: `${product.posX * 100}%`, top: `${product.posY * 100}%` }"
                        :title="product.title ?? 'Produto'"
                        @click="selectedProduct = product"
                    >
                        <ShoppingBag class="h-4 w-4 text-lavender-deep" />
                    </button>
                </template>
            </div>

            <!-- Ações -->
            <div class="flex items-center gap-1.5 px-2 py-1 sm:px-0">
                <button
                    type="button"
                    class="rounded-full p-2 transition hover:bg-lavender-tint active:scale-90"
                    :aria-label="post.liked ? 'Descurtir' : 'Curtir'"
                    @click="toggleLike"
                >
                    <Heart
                        class="h-6 w-6 transition"
                        :class="post.liked ? 'fill-pink-500 stroke-pink-500' : 'stroke-black/60'"
                    />
                </button>
                <span class="text-sm font-medium">{{ post.likeCount }}</span>
                <span v-if="post.products.length" class="ml-auto mr-3 flex items-center gap-1.5 text-sm text-black/60">
                    <ShoppingBag class="h-4 w-4 text-lavender-deep" />
                    {{ post.products.length }} produto(s)
                </span>
            </div>

            <p v-if="post.caption" class="px-4 pb-2 text-[15px] sm:px-0">{{ post.caption }}</p>

            <!-- Lista de produtos -->
            <section v-if="post.products.length" class="mx-3 mt-2 rounded-2xl bg-white p-2 shadow-sm sm:mx-0">
                <h2 class="px-2 pb-1 pt-2 font-bold">Produtos nesta foto</h2>
                <button
                    v-for="product in post.products"
                    :key="`row-${product.id}`"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl p-2 text-left transition hover:bg-lavender-tint"
                    @click="selectedProduct = product"
                >
                    <img
                        v-if="product.imageUrl"
                        :src="product.imageUrl"
                        alt=""
                        class="h-12 w-12 rounded-lg object-cover"
                    />
                    <span v-else class="flex h-12 w-12 items-center justify-center rounded-lg bg-black/5">
                        <ShoppingBag class="h-5 w-5 text-black/30" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold">{{ product.title ?? 'Produto' }}</span>
                        <span v-if="product.price" class="block text-sm text-lavender-deep">{{ product.price }}</span>
                    </span>
                    <ChevronRight class="h-4 w-4 shrink-0 text-black/30" />
                </button>
            </section>
        </article>

        <!-- Folha do produto -->
        <Teleport to="body">
            <div
                v-if="selectedProduct"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center"
                @click.self="selectedProduct = null"
            >
                <div class="w-full max-w-md rounded-t-3xl bg-white p-5 shadow-xl sm:rounded-3xl">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <h3 class="text-lg font-bold leading-snug">{{ selectedProduct.title ?? 'Produto' }}</h3>
                        <button
                            type="button"
                            class="rounded-full p-1.5 text-black/50 hover:bg-lavender-tint"
                            @click="selectedProduct = null"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <img
                        v-if="selectedProduct.imageUrl"
                        :src="selectedProduct.imageUrl"
                        alt=""
                        class="mb-3 h-44 w-full rounded-2xl object-cover"
                    />
                    <p v-if="selectedProduct.price" class="mb-4 text-base font-semibold text-lavender-deep">
                        {{ selectedProduct.price }}
                    </p>
                    <a
                        :href="selectedProduct.sourceUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-lavender py-3.5 font-semibold text-white transition hover:bg-lavender-deep"
                    >
                        <ExternalLink class="h-5 w-5" />
                        Ver produto
                    </a>
                </div>
            </div>
        </Teleport>
    </LariaLayout>
</template>
