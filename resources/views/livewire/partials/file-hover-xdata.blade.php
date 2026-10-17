{{-- Sdílená Alpine logika hover panelu souboru. Použití: x-data="@include('livewire.partials.file-hover-xdata')".
     V JS jen jednoduché uvozovky (vkládá se do atributu x-data="..."). Kotva = x-ref="anchor", jinak samotný prvek. --}}
{
    open: false, t: null, x: 0, y: 0,
    enter() {
        if (!window.matchMedia('(hover: hover)').matches) return;
        clearTimeout(this.t);
        this.t = setTimeout(() => this.show(), 300);
    },
    leave() { clearTimeout(this.t); this.open = false; },
    show() {
        const a = this.$refs.anchor || this.$el;
        const r = a.getBoundingClientRect(), w = 288, gap = 8, vw = window.innerWidth, vh = window.innerHeight;
        let left = r.right + gap;
        if (left + w > vw - gap) left = r.left - gap - w;
        if (left < gap) left = Math.max(gap, Math.min(r.left, vw - w - gap));
        this.x = left;
        this.y = Math.max(gap, r.top);
        this.open = true;
        this.$nextTick(() => {
            const h = this.$refs.pop ? this.$refs.pop.offsetHeight : 0;
            if (this.y + h > vh - gap) this.y = Math.max(gap, vh - h - gap);
        });
    }
}
