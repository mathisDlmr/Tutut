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
    .bg-success {
        background-color: #22c55e; /* vert style Tailwind green-500 */
    }
    .bg-danger {
        background-color: #ef4444; /* rouge style Tailwind red-500 */
    }
    .bg-primary {
        background-color: #3b82f6; /* bleu style Tailwind blue-500 */
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
    <div class="flex gap-2" x-data="{ counted: @js($isCounted) }">
    <button
        type="button"
        @click="counted = true; $wire.call('toggleCreneauCompted', {{ $creneau->id }}, true)"
        class="px-2 py-1 rounded text-white font-semibold"
        :class="counted ? 'bg-success' : 'bg-primary'"
    >
        Compter
    </button>

    <button
        type="button"
        @click="counted = false; $wire.call('toggleCreneauCompted', {{ $creneau->id }}, false)"
        class="px-2 py-1 rounded text-white font-semibold"
        :class="counted === false ? 'bg-danger' : 'bg-primary'"
    >
        Décompter
    </button>
</div>

</div>

