{{--
    Vlastní potvrzovací okno místo prohlížečového confirm().

    Funguje automaticky pro všechny prvky s wire:confirm="text" (i v SVG).
    Volitelně na stejném prvku:
      data-confirm-title="Nadpis"      (výchozí "Potvrzení")
      data-confirm-ok="Smazat"         (text tlačítka, výchozí "Potvrdit")
      data-confirm-danger              (červené tlačítko - nevratné akce)

    Z JS: bpsConfirm('Text', { title, ok, danger }).then(ok => ...)
--}}
<div id="bps-confirm" class="fixed inset-0 z-[60] items-center justify-center p-4"
    style="display: none; background: rgba(0, 0, 0, 0.6);" role="dialog" aria-modal="true" aria-labelledby="bps-confirm-title">
    <div class="w-full max-w-md rounded-xl shadow-2xl bg-white dark:bg-bambu-dark-2 border border-gray-200 dark:border-bambu-dark-4">
        <div class="px-5 pt-5 pb-4">
            <h3 id="bps-confirm-title" class="text-base font-semibold text-gray-900 dark:text-bambu-text"></h3>
            <p id="bps-confirm-message" class="mt-2 text-sm text-gray-600 dark:text-bambu-text-dim" style="white-space: pre-line;"></p>
        </div>
        <div class="px-5 py-3 flex justify-end gap-2 border-t border-gray-100 dark:border-bambu-dark-4">
            <button type="button" id="bps-confirm-cancel"
                class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-700 dark:text-bambu-text">Zrušit</button>
            <button type="button" id="bps-confirm-ok"
                class="px-4 py-2 text-sm font-medium rounded-lg text-white"></button>
        </div>
    </div>
</div>

<script>
(function () {
    const root    = document.getElementById('bps-confirm');
    const titleEl = document.getElementById('bps-confirm-title');
    const msgEl   = document.getElementById('bps-confirm-message');
    const okBtn   = document.getElementById('bps-confirm-ok');
    const noBtn   = document.getElementById('bps-confirm-cancel');
    const OK_CLS  = { danger: ['bg-red-600', 'hover:bg-red-700'], normal: ['bg-green-600', 'hover:bg-green-700'] };
    let pending   = null;

    function close(result) {
        root.style.display = 'none';
        document.removeEventListener('keydown', onKey, true);
        const resolve = pending;
        pending = null;
        if (resolve) resolve(result);
    }

    function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); close(false); }
        if (e.key === 'Enter')  { e.preventDefault(); close(true); }
    }

    window.bpsConfirm = function (message, opts = {}) {
        if (pending) close(false);
        titleEl.textContent = opts.title || 'Potvrzení';
        msgEl.textContent   = message || 'Opravdu pokračovat?';
        okBtn.textContent   = opts.ok || 'Potvrdit';
        okBtn.classList.remove(...OK_CLS.danger, ...OK_CLS.normal);
        okBtn.classList.add(...(opts.danger ? OK_CLS.danger : OK_CLS.normal));
        root.style.display = 'flex';
        document.addEventListener('keydown', onKey, true);
        setTimeout(() => (opts.danger ? noBtn : okBtn).focus(), 0);
        return new Promise(resolve => { pending = resolve; });
    };

    okBtn.addEventListener('click', () => close(true));
    noBtn.addEventListener('click', () => close(false));
    root.addEventListener('click', e => { if (e.target === root) close(false); });

    // wire:confirm - klik zachytíme dřív než Livewire (capture fáze), zeptáme
    // se vlastním oknem a po potvrzení klik zopakujeme. Livewire si pak sám
    // volá confirm(), ten na tu chvíli odpoví "ano".
    let replaying = false;
    document.addEventListener('click', function (e) {
        if (replaying) return;
        const el = e.target.closest && e.target.closest('[wire\\:confirm]');
        if (!el) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        bpsConfirm(el.getAttribute('wire:confirm'), {
            title:  el.dataset.confirmTitle,
            ok:     el.dataset.confirmOk,
            danger: el.hasAttribute('data-confirm-danger'),
        }).then(ok => {
            if (!ok || !el.isConnected) return;
            const nativeConfirm = window.confirm;
            replaying      = true;
            window.confirm = () => true;
            try {
                el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
            } finally {
                window.confirm = nativeConfirm;
                replaying      = false;
            }
        });
    }, true);
})();
</script>
