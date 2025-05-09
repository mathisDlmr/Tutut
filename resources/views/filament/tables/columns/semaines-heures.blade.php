{{-- resources/views/filament/tables/columns/semaines-heures.blade.php --}}
<div>
    @php
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
        <div class="text-gray-600 w-full">
            @if(count($lignes) > 0)
                {!! implode('<br>', $lignes) !!}
            @else
                <div class="flex items-center space-x-2 p-2 text-gray-400 text-sm">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" />
                        <line x1="8" y1="12" x2="16" y2="12" stroke="currentColor" stroke-width="1.5" />
                    </svg>
                    <span>Aucun résultat disponible</span>
                </div>
            @endif
        </div>
    </div>
</div>
