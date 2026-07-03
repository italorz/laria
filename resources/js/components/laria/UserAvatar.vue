<script setup lang="ts">
import { User } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        src?: string | null;
        alt?: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { size: 'md' },
);

const sizes = { sm: 'h-8 w-8', md: 'h-10 w-10', lg: 'h-20 w-20' };
const icon = { sm: 'h-4 w-4', md: 'h-5 w-5', lg: 'h-9 w-9' };
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-lavender/25"
        :class="sizes[size]"
    >
        <img v-if="src" :src="src" :alt="alt ?? ''" class="h-full w-full object-cover" />
        <User v-else class="text-lavender-deep" :class="icon[size]" />
    </span>
</template>
