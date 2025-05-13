@props([
    'creneau',
    'isCounted',
])

@php
    $uvs = collect($creneau->inscriptions)
        ->flatMap(fn($i) => json_decode($i->enseignements_souhaites))
        ->unique()
        ->implode(', ');
@endphp

<style>
    .bg-success { /* On utilise des nouvelles classes pour bypass le JIT de Tailwind */
        background-color: #22c55e;
    }
    .bg-danger {
        background-color: #ef4444;
    }
    .bg-primary {
        background-color: #3b82f6;
    }
</style>

<div class="bg-white p-4 space-y-2 rounded-lg shadow border">
    <div class="text-sm text-gray-500 flex items-center gap-1">
        <x-heroicon-o-calendar-days class="h-4 w-4 text-primary-500" />
        {{ ucfirst($creneau->start->translatedFormat('l d F')) }}
    </div>

    <div class="text-sm text-gray-500 flex items-center gap-1">
        <x-heroicon-o-clock class="h-4 w-4 text-primary-500" />
        {{ $creneau->start->format('H:i') }} - {{ $creneau->end->format('H:i') }}
    </div>

    <div class="text-sm text-gray-500 flex items-center gap-1">
        <x-heroicon-o-user-group class="h-4 w-4 text-primary-500" />
        {{ $creneau->inscriptions->count() }} inscrit·e·s
    </div>

    <div class="text-sm text-gray-500 flex items-center gap-1">
        <x-heroicon-o-academic-cap class="h-4 w-4 text-primary-500" />
        {{ $uvs ?: '—' }}
    </div>

    <div 
        class="flex gap-2" 
        x-data="{ 
            counted: @js($isCounted), 
            loading: false 
        }"
    >
        <button
            type="button"
            @click="
                loading = true;
                $wire.call('toggleCreneauCompted', {{ $creneau->id }}, true)
                    .then(() => counted = true)
                    .finally(() => loading = false)
            "
            :disabled="loading"
            class="px-3 py-1.5 min-w-[100px] rounded text-white font-semibold flex items-center justify-center transition"
            :class="counted ? 'bg-success' : 'bg-primary'"
        >
            <template x-if="loading && !counted">
                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </template>
            <span x-show="!loading || !counted">Présent·e</span>
        </button>

        <button
            type="button"
            @click="
                loading = true;
                $wire.call('toggleCreneauCompted', {{ $creneau->id }}, false)
                    .then(() => counted = false)
                    .finally(() => loading = false)
            "
            :disabled="loading"
            class="px-3 py-1.5 min-w-[100px] rounded text-white font-semibold flex items-center justify-center transition"
            :class="counted == false ? 'bg-danger' : 'bg-primary'"
        >
            <template x-if="loading && counted">
                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </template>
            <span x-show="!loading || counted !== false">Absent·e</span>
        </button>
    </div>
</div>
