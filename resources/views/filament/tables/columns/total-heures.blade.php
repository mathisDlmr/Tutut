{{-- resources/views/filament/tables/columns/total-heures.blade.php --}}
<div>
    @php
    // Récupération de l'état fourni par la méthode getStateUsing
    $total = $getState();
    @endphp
    <div class="flex items-center">
        <div class="font-bold text-primary-600">
            @if($total > 0) Total : {{ $total }} h @endif
        </div>
    </div>
</div>