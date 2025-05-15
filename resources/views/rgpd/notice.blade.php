<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consentement RGPD</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-900">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white shadow-md rounded-lg p-8 max-w-xl w-full">
            <h1 class="text-2xl font-bold mb-4">Consentement RGPD</h1>
            <div>
                <p>
                    Pour permettre le bon fonctionnement de l'application, nous collectons certaines de vos données personnelles : 
                </p>
                <ul class="list-disc ml-8 mb-6">
                    <li>Email utc</li>
                    <li>Nom et prénom</li>
                    <li>Inscriptions aux créneaux</li>
                </ul>
            </div>
            <div>
                <p>De manière optionnelle pour les tuteur.ice.s : </p>
                <ul class="list-disc ml-8 mb-6">
                    <li>Langues maitrisées</li>
                    <li>UVs proposées</li>
                    <li>Créneaux proposés</li>
                    <li>Heures supplémentaires déclarées</li>
                </ul>
            </div>
            <p class="mb-6 italic">
                Ces données sont stockées de façon sécurisée sur les serveurs de l'UTC et peuvent potentiellement être exportées par le Bureau de Vie Etudiante afin d'améliorer Tut'ut
            </p>
            <form method="POST" action="{{ route('rgpd.accept') }}">
                @csrf
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
                    J’accepte
                </button>
            </form>
        </div>
    </div>
</body>
</html>
