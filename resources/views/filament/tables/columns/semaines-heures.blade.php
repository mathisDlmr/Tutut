{{-- resources/views/filament/tables/columns/semaines-heures.blade.php --}}
<div>
    @php
        // Récupération de l'état fourni par la méthode getStateUsing
        $data = $getState();
        $lignes = [];
        
        if (is_array($data)) {
            foreach ($data as $item) {
                $semaine = $item['semaine'];
                $heures = $item['heures'];
                $validated = $item['validated'];
                $commentaire = $item['commentaire_bve'];
                
                $validatedIcon = $validated 
                    ? '<i class="text-success-500 ti ti-check-circle"></i>' 
                    : '<i class="text-danger-500 ti ti-x-circle"></i>';
                
                $ligne = "<span class='font-medium'>Semaine {$semaine->numero} :</span> {$heures} h $validatedIcon";
                
                if ($commentaire) {
                    $ligne .= ' <i class="text-warning-500 ti ti-message" title="' . htmlspecialchars($commentaire) . '"></i>';
                }
                
                $lignes[] = $ligne;
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