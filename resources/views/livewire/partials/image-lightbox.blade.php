{{--
    Zvětšený obrázek v okně (zavřít křížkem, klikem vedle nebo klávesou Esc).
    Otevření: $dispatch('open-lightbox', { src: 'url', title: 'popisek' })
--}}
<div wire:ignore x-data="{ open: false, src: '', title: '' }"
    x-on:open-lightbox.window="src = $event.detail.src; title = $event.detail.title || ''; open = true"
    x-on:keydown.escape.window="open = false"
    x-show="open" x-cloak x-transition.opacity
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="background: rgba(0, 0, 0, 0.85);"
    x-on:click.self="open = false">
    <button type="button" x-on:click="open = false" title="Zavřít (Esc)"
        class="absolute top-3 right-3 w-10 h-10 rounded-full text-white text-2xl leading-none flex items-center justify-center"
        style="background: rgba(0, 0, 0, 0.6);">×</button>
    <figure class="flex flex-col items-center" x-on:click.self="open = false">
        <img :src="src" alt="" class="rounded-lg shadow-2xl object-contain"
            style="max-width: 95vw; max-height: 85vh; background: #000;">
        <figcaption x-show="title" x-text="title" class="mt-2 text-sm text-gray-200 text-center"></figcaption>
    </figure>
</div>
