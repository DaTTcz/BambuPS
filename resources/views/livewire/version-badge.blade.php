<div class="flex items-center">
    @if($this->updateAvailable)
        <button wire:click="startUpdate"
            class="flex items-center space-x-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-green-50 dark:bg-bambu-dark-3 text-green-700 dark:text-bambu-green border border-green-200 dark:border-bambu-dark-4 hover:bg-green-100 dark:hover:bg-bambu-dark-4 transition-colors">
            <span class="text-gray-400 dark:text-bambu-text-dim">{{ $this->currentVersion }}</span>
            <span>⬆️ aktualizuj na {{ $this->latestVersion }}</span>
        </button>
    @else
        <span class="px-2 py-1 text-xs text-gray-400 dark:text-bambu-text-dim font-mono">{{ $this->currentVersion }}</span>
    @endif

    {{-- Modal: Live progress checklist aktualizace --}}
    @if($showUpdateModal)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
            x-data="{
                steps: @js(array_keys($updateSteps)),
                current: 0,
                async run() {
                    for (let i = 0; i < this.steps.length; i++) {
                        this.current = i;
                        await $wire.runUpdateStep(this.steps[i]);
                        if (@this.updateFailed) return;
                    }
                    this.current = this.steps.length;
                    await $wire.finishUpdate();
                }
            }"
            x-init="run()">
            <div class="bg-white dark:bg-bambu-dark-2 border border-gray-100 dark:border-bambu-dark-4 rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-bambu-text mb-5">
                    Aktualizuji na {{ $targetVersion }}...
                </h3>

                <ul class="space-y-3">
                    @foreach($updateSteps as $key => $label)
                        <li class="flex items-center space-x-3 text-sm" wire:key="update-step-{{ $key }}">
                            @if(($updateStatus[$key] ?? 'pending') === 'done')
                                <span class="text-green-600 dark:text-bambu-green shrink-0">✅</span>
                            @elseif($updateFailed)
                                <span class="shrink-0 text-gray-300 dark:text-bambu-text-dim">○</span>
                            @else
                                <span x-show="steps[current] === '{{ $key }}'"
                                    class="shrink-0 inline-block w-4 h-4 border-2 border-gray-300 dark:border-bambu-dark-4 border-t-green-600 dark:border-t-bambu-green rounded-full animate-spin"></span>
                                <span x-show="steps[current] !== '{{ $key }}'"
                                    class="shrink-0 text-gray-300 dark:text-bambu-text-dim">○</span>
                            @endif
                            <span class="{{ ($updateStatus[$key] ?? 'pending') === 'done'
                                ? 'text-gray-800 dark:text-bambu-text'
                                : 'text-gray-500 dark:text-bambu-text-dim' }}">
                                {{ $label }}
                            </span>
                        </li>
                    @endforeach
                </ul>

                @if($updateFailed)
                    <div class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <p class="text-sm font-medium text-red-700 dark:text-red-400 mb-1">Aktualizace selhala.</p>
                        <pre class="text-xs text-red-600 dark:text-red-400 whitespace-pre-wrap max-h-32 overflow-y-auto">{{ Str::limit($updateError, 500) }}</pre>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button wire:click="closeUpdateModal"
                            class="px-4 py-2 text-sm bg-gray-100 dark:bg-bambu-dark-3 hover:bg-gray-200 dark:hover:bg-bambu-dark-4 text-gray-700 dark:text-bambu-text rounded-lg">
                            Zavřít
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
