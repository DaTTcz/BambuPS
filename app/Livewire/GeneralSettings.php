<?php

namespace App\Livewire;

use App\Support\Timezone;
use Livewire\Component;

class GeneralSettings extends Component
{
    public string $timezone = '';

    public function mount(): void
    {
        $this->timezone = Timezone::get();
    }

    /** Zóny seskupené podle kontinentu, Evropa první */
    public function getTimezoneGroupsProperty(): array
    {
        $groups = [];
        foreach (\DateTimeZone::listIdentifiers() as $tz) {
            $region = str_contains($tz, '/') ? strstr($tz, '/', true) : 'Ostatní';
            $groups[$region][] = $tz;
        }
        uksort($groups, fn ($a, $b) => [$a !== 'Europe', $a] <=> [$b !== 'Europe', $b]);
        return $groups;
    }

    public function getPreviewProperty(): string
    {
        $tz = Timezone::isValid($this->timezone) ? $this->timezone : 'UTC';
        return now($tz)->format('d.m.Y H:i') . ' (UTC' . now($tz)->format('P') . ')';
    }

    public function save(): void
    {
        if (!Timezone::isValid($this->timezone)) {
            $this->addError('timezone', 'Neplatná časová zóna.');
            return;
        }
        Timezone::set($this->timezone);
        $this->dispatch('toast', type: 'success', message: 'Časová zóna uložena');
    }

    public function render()
    {
        return view('livewire.general-settings');
    }
}
