<x-Filament::Page>
    @foreach ($GroupedCreneaux->groupBy(fn ($Creneau) => \Illuminate\Support\Carbon::parse($Creneau->Start)->format('Y-m-d')) as $Date => $CreneauxDuJour)
        <x-Filament::Section>
            <x-slot name="heading">{{ \Illuminate\Support\Carbon::parse($Date)->translatedFormat('l j F Y') }}</x-slot>

            <div class="space-y-10">
                @foreach ($CreneauxDuJour->groupBy(fn ($Creneau) => \Illuminate\Support\Carbon::parse($Creneau->Start)->format('H:i')) as $Heure => $CreneauxAHeure)
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 border-b pb-1 mb-4">HEURE : {{ $Heure }}</h3>

                        <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                            @foreach ($CreneauxAHeure as $Creneau)
                                <x-Filament::Card class="h-full p-5 shadow-sm border border-gray-200 flex flex-col justify-between">
                                    <div class="space-y-3">
                                        <div class="text-sm text-gray-700 font-semibold uppercase">
                                            {{ \Illuminate\Support\Carbon::parse($Creneau->Start)->format('H:i') }} - {{ \Illuminate\Support\Carbon::parse($Creneau->End)->format('H:i') }}
                                        </div>

                                        <div class="text-sm text-gray-500">
                                            <span class="font-medium uppercase">Salle :</span> {{ $Creneau->FkSalle }}
                                        </div>

                                        <div class="text-sm text-gray-500">
                                            <span class="font-medium uppercase">Semaine :</span> {{ $Creneau->Semaine->Numero }} ({{ $Creneau->Semaine->Semestre->Code }})
                                        </div>

                                        <div class="text-sm text-gray-700 space-y-1 pt-2">
                                            <div><span class="font-medium uppercase">Tuteur 1 :</span> {{ $Creneau->Tutor1->FirstName ?? '—' }}</div>
                                            <div><span class="font-medium uppercase">Tuteur 2 :</span> {{ $Creneau->Tutor2->FirstName ?? '—' }}</div>
                                        </div>

                                        @php
                                            $Uvs = collect();
                                            if ($Creneau->Tutor1) {
                                                $Uvs = $Uvs->merge($Creneau->Tutor1->ProposedUvs->pluck('Code'));
                                            }
                                            if ($Creneau->Tutor2) {
                                                $Uvs = $Uvs->merge($Creneau->Tutor2->ProposedUvs->pluck('Code'));
                                            }
                                            $Uvs = $Uvs->unique()->sort();
                                        @endphp

                                        @if ($Uvs->isNotEmpty())
                                            <div class="text-sm">
                                                <span class="font-medium uppercase text-gray-600">UVs :</span>
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    @foreach ($Uvs as $Uv)
                                                        <span class="bg-gray-100 text-gray-700 px-2 py-0.5 text-xs rounded-full">{{ $Uv }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex gap-2 mt-4">
                                        @if (!$Creneau->Tutor1Id)
                                            <x-Filament::Button wire:click="shotgun({{ $Creneau->Id }}, 1)" color="primary" size="sm" class="flex-1">
                                                Shotgun 1
                                            </x-Filament::Button>
                                        @endif

                                        @if (!$Creneau->Tutor2Id && $Creneau->Tutor1Id !== auth()->id())
                                            <x-Filament::Button wire:click="shotgun({{ $Creneau->Id }}, 2)" color="primary" size="sm" class="flex-1">
                                                Shotgun 2
                                            </x-Filament::Button>
                                        @endif
                                    </div>
                                </x-Filament::Card>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-Filament::Section>
    @endforeach
</x-Filament::Page>
