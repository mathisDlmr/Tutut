{{-- resources/views/filament/tables/columns/total-heures.blade.php --}}
<div>
    @php
        // Récupération de l'état fourni par la méthode getStateUsing
        $total = $getState();
    @endphp

    <div class="flex items-center">
        <div class="font-bold text-primary-600">
            Total : @if($total > 0) {{ $total }} h @else — @endif
        </div>
    </div>
</div>