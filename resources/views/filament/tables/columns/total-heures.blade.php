{{-- resources/views/filament/tables/columns/total-heures.blade.php --}}
<div>
    @php
        $semaines = \App\Models\Semaine::whereHas('semestre', fn($query) => $query->where('is_active', true))
            ->pluck('id');
        
        $totalComptabilite = \DB::table('comptabilite')
            ->where('fk_user', $getRecord()->id)
            ->whereIn('fk_semaine', $semaines)
            ->sum('nb_heures');
        
        $total = $totalComptabilite;
    @endphp
    
    <div class="flex items-center">
        <div class="font-bold text-primary-600">
            Total : 
            @if($total > 0)
                {{ $total }} h
            @else
                —
            @endif
        </div>
    </div>
</div>