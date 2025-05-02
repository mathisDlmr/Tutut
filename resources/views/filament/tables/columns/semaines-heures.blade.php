{{-- resources/views/filament/tables/columns/semaines-heures.blade.php --}}
<div>
    @php
        $semaines = \App\Models\Semaine::whereHas('semestre', fn($query) => $query->where('is_active', true))
            ->orderBy('numero')
            ->get();
        
        $comptabilites = \App\Models\Comptabilite::where('fk_user', $getRecord()->id)
            ->whereIn('fk_semaine', $semaines->pluck('id'))
            ->get()
            ->keyBy('fk_semaine');
        
        $lignes = [];
        
        foreach ($semaines as $semaine) {
            $comptabilite = $comptabilites->get($semaine->id);
            $nbHeuresCompta = $comptabilite ? $comptabilite->nb_heures : 0;
            
            if ($nbHeuresCompta > 0) {
                $lignes[] = "<span class='font-medium'>Semaine {$semaine->numero} :</span> {$nbHeuresCompta} h";
            }
        }
    @endphp
    
    <div class="flex items-center">
        <div class="text-gray-600">
            @if(count($lignes) > 0)
                {!! implode('<br>', $lignes) !!}
            @else
                —
            @endif
        </div>
    </div>
</div>