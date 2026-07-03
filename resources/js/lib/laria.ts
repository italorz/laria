// Tipos e utilitários compartilhados das páginas do Laria.

export interface Author {
    id: number;
    displayName: string;
    avatarUrl: string | null;
}

export interface ProductTag {
    id?: string;
    sourceUrl: string;
    title: string | null;
    price: string | null;
    imageUrl: string | null;
    posX: number;
    posY: number;
}

export interface Post {
    id: string;
    imageUrl: string;
    originalImageUrl: string | null;
    caption: string | null;
    createdAt: string;
    likeCount: number;
    liked: boolean;
    author: Author;
    products: ProductTag[];
}

/** Lê o token CSRF do cookie XSRF-TOKEN emitido pelo Laravel. */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * fetch com credenciais + CSRF para os endpoints JSON (like, scrape, generate).
 * Lança Error com a mensagem retornada pelo backend quando a resposta não é 2xx.
 */
export async function apiFetch<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers ?? {}),
        },
    });

    const data = await response.json().catch(() => null);
    if (!response.ok) {
        const message =
            (data && (data.error || data.message)) || 'Algo deu errado. Tente novamente.';
        throw new Error(message);
    }
    return data as T;
}

export function timeAgo(iso: string): string {
    const diffMs = Date.now() - new Date(iso).getTime();
    const minutes = Math.floor(diffMs / 60000);
    if (minutes < 1) return 'agora';
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} h`;
    return `${Math.floor(hours / 24)} d`;
}
