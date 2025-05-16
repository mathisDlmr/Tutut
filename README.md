# Documentation Tut'ut

## Présentation générale

Tut'ut est une application de gestion de tutorat universitaire basée sur le framework Filament pour Laravel. Elle permet aux tuteurs et tutorés de gérer des créneaux de tutorat, des inscriptions, et d'organiser les séances d'accompagnement.

L'application est conçue pour répondre aux besoins spécifiques des établissements d'enseignement supérieur en facilitant :
- L'organisation des séances de tutorat entre pairs
- La mise en relation tuteurs/tutorés selon les matières (UVs)
- La gestion administrative des tuteurs employés
- Le suivi des séances et la comptabilité associée

## Architecture technique

### Stack technologique

L'application repose sur les technologies suivantes :
- **Laravel** : Framework PHP pour le backend et la logique métier
- **Filament** : Framework d'administration pour Laravel qui fournit une interface utilisateur moderne
- **MySQL/MariaDB** : Système de gestion de base de données relationnelle
- **Livewire** : Framework JavaScript minimaliste pour des interactions dynamiques côté client
- **Alpine.js** : Framework JavaScript léger pour la réactivité de l'UI

### Structure des répertoires

```
app/
├── Enums/              # Énumérations (Roles, etc.)
├── Filament/           # Composants Filament
│   ├── Pages/          # Pages personnalisées
│   │   ├── Admin/      # Ressources accessibles aux administrateurs
│   │   ├── Tutor/      # Ressources accessibles aux tuteurs
│   │   └── Tutee/      # Ressources accessibles aux tutorés
│   └── Widgets/        # Widgets pour le tableau de bord
├── Http/               # Controllers et Middlewares
│   ├── Controllers/    # Contrôleurs Laravel
│   └── Middleware/     # Middlewares (ex: EnsureRgpdAccepted)
├── Models/             # Modèles Eloquent
└── Providers/          # Service providers
    └── Filament/       # Provider pour Filament
```

### Intégration de Filament

Filament est configuré via le `AdminPanelProvider` qui utilise une approche orientée panneaux (panel-based). Chaque panneau peut avoir ses propres :
- Ressources (CRUD)
- Pages personnalisées
- Widgets
- Thème et configuration visuelle

Exemple d'enregistrement de ressource dans `AdminPanelProvider.php` :

```php
->resources([
    AdminTuteursEmployesResource::class,
    AdminSemestreResource::class,
    AdminSemaineResource::class,
    ComptabiliteResource::class,
    SalleResource::class,
    // ...
])
```

### Gestion des autorisations

L'application utilise un système d'autorisation basé sur les rôles, implémenté via les méthodes `canAccess()` dans chaque ressource :

```php
public static function canAccess(): bool
{
    $user = Auth::user();
    return $user && (
        $user->role === Roles::Administrator->value ||
        $user->role === Roles::EmployedPrivilegedTutor->value
    );
}
```

### Middleware personnalisé

Un middleware RGPD vérifie que les utilisateurs ont accepté les conditions d'utilisation :

```php
public function handle(Request $request, Closure $next)
{
    if (Auth::check() && !Auth::user()->rgpd_accepted_at && !$request->routeIs('rgpd.*')) {
        return redirect()->route('rgpd.accept');
    }

    return $next($request);
}
```

## Structure de l'application

L'application est organisée autour du panel d'administration Filament, configuré dans `app/Providers/Filament/AdminPanelProvider.php`. Ce panel est accessible via l'URL `/tutut` et est sécurisé par authentification.

Le fichier `AdminPanelProvider.php` définit :
- La configuration visuelle (couleurs, nom de marque)
- Les ressources disponibles (classées par rôle)
- Les pages accessibles
- Les widgets du tableau de bord
- Les middlewares de sécurité (dont `EnsureRgpdAccepted` pour la conformité RGPD)

L'application utilise une architecture basée sur les ressources Filament, qui combine :
- Des modèles Eloquent (app/Models)
- Des ressources Filament (app/Filament/Resources)
- Des pages personnalisées (app/Filament/Pages)
- Des widgets (app/Filament/Widgets)

### Rôles utilisateurs

L'application définit les rôles suivants (définis dans `App\Enums\Roles`) :

- **Administrator** : Administrateurs avec accès complet à toutes les fonctionnalités
  - Peuvent gérer les utilisateurs, les semestres, les salles et toute la configuration
  - Ont accès aux statistiques et à la comptabilité globale
  - Peuvent envoyer des emails aux utilisateurs
  - Code d'implémentation:
    ```php
    case Administrator = 'admin';
    
    public function isAdministrator(): bool
    {
        return $this === self::Administrator;
    }
    ```
  
- **EmployedPrivilegedTutor** : Tuteurs employés avec privilèges supplémentaires
  - Mêmes privilèges que les tuteurs employés standard
  - Accès à certaines fonctionnalités d'administration comme les paramètres
  - Peuvent gérer le calendrier et certaines ressources administratives
  - Code d'implémentation:
    ```php
    case EmployedPrivilegedTutor = 'employedPrivilegedTutor';
    
    public function isEmployedPrivilegedTutor(): bool
    {
        return $this === self::EmployedPrivilegedTutor;
    }
    ```
  
- **EmployedTutor** : Tuteurs employés standard
  - Peuvent créer des créneaux plus tôt que les tuteurs non-employés
  - Ont accès à des statistiques personnelles et à leur comptabilité
  - Gèrent leurs UVs et leurs créneaux
  - Code d'implémentation:
    ```php
    case EmployedTutor = 'employedTutor';
    
    public function isEmployedTutor(): bool
    {
        return $this === self::EmployedTutor;
    }
    ```
  
- **Tutor** : Tuteurs non-employés
  - Peuvent créer des créneaux selon un calendrier défini par l'administration
  - Gèrent leurs UVs proposées et leurs créneaux
  - Accèdent aux feedback de leurs sessions
  - Code d'implémentation:
    ```php
    case Tutor = 'tutor';
    
    public function isTutor(): bool
    {
        return $this === self::Tutor;
    }
    ```
  
- **Tutee** : Tutorés (étudiants bénéficiant du tutorat)
  - Peuvent s'inscrire aux créneaux disponibles
  - Peuvent demander à devenir tuteur
  - Voient leur planning de tutorat
  - Code d'implémentation:
    ```php
    case Tutee = 'tutee';
    
    public function isTutee(): bool
    {
        return $this === self::Tutee;
    }
    ```

#### Système de contrôle d'accès

Le contrôle d'accès basé sur les rôles est implémenté via la méthode `canAccess()` dans chaque ressource. Exemple pour une ressource réservée aux administrateurs :

```php
public static function canAccess(): bool
{
    $user = Auth::user();
    return $user && Auth::user()->role === Roles::Administrator->value;
}
```

Pour une ressource accessible à plusieurs rôles :

```php
public static function canAccess(): bool
{
    $user = Auth::user();
    return $user && (
        Auth::user()->role === Roles::EmployedPrivilegedTutor->value
        || Auth::user()->role === Roles::EmployedTutor->value
        || Auth::user()->role === Roles::Tutor->value
    );
}
```

#### Vérification des rôles dans le code

L'enum `Roles` fournit des méthodes de vérification qui peuvent être utilisées dans tout le code :

```php
if (Auth::user()->role === Roles::Administrator->value) {
    // Actions réservées aux administrateurs
}
```

Ou en utilisant les méthodes de l'enum :

```php
$role = Roles::from(Auth::user()->role);
if ($role->isAdministrator() || $role->isEmployedPrivilegedTutor()) {
    // Actions réservées aux administrateurs et tuteurs employés privilégiés
}
```

### Fonctionnalités principales

L'application comprend trois grands groupes de fonctionnalités, accessibles selon le rôle de l'utilisateur connecté :

#### 1. Administration (Admin)

Ressources accessibles aux administrateurs :
- **TuteursEmployesResource** : Gestion des tuteurs employés
  - Création et modification des comptes tuteurs
  - Attribution des rôles (admin, tuteur employé, etc.)
  - Gestion groupée de comptes via importation d'emails
  
- **SemestreResource** : Gestion des semestres universitaires
  - Création de nouveaux semestres (ex: "A25" pour automne 2025)
  - Définition des dates de début/fin et périodes d'examens
  - Activation du semestre courant
  
- **SemaineResource** : Gestion des semaines dans un semestre
  - Numérotation et paramétrage des semaines
  - Définition des périodes spéciales (examens, vacances)
  
- **ComptabiliteResource** : Gestion de la comptabilité
  - Suivi des heures effectuées par les tuteurs
  - Gestion de la paie et des heures supplémentaires
  - Génération de rapports
  
- **SalleResource** : Gestion des salles disponibles
  - Ajout et modification des salles
  - Configuration de la disponibilité

Pages spécifiques :
- **SettingsPage** : Configuration des paramètres de l'application
  - Définition des jours et heures d'accès aux créneaux selon le rôle
  - Configuration des règles d'annulation
  - Paramètres généraux du système
  
- **CalendarManager** : Gestion du calendrier
  - Création et modification d'événements
  - Définition de règles d'exception pour certaines dates
  - Vue globale de la planification
  
- **SendEmail** : Envoi d'emails aux utilisateurs
  - Communication avec des groupes d'utilisateurs
  - Modèles d'emails pour différentes occasions
  - Suivi des envois

#### 2. Tuteurs (Tutor)

Ressources accessibles aux tuteurs :
- **CreneauResource** : Gestion des créneaux de tutorat ("Shotgun Créneaux")
  - Réservation de plages horaires selon disponibilités
  - Association avec un autre tuteur possible
  - Vue calendrier des créneaux disponibles et réservés
  
- **ComptabiliteTutorResource** : Suivi de leur comptabilité
  - Visualisation des heures effectuées
  - Suivi de la rémunération
  - Historique des sessions
  
- **FeedbackResource** : Gestion des retours sur les séances de tutorat
  - Création de rapports post-séance
  - Suivi des présences
  - Notes et commentaires sur les sessions
  
- **TutorApplicationResource** : Gestion des candidatures pour devenir tuteur
  - Suivi de l'état des candidatures
  - Processus d'approbation

Page spécifique :
- **TutorManageUvs** : Gestion des UVs proposées par le tuteur
  - Sélection des UVs dans lesquelles le tuteur se sent compétent
  - Mise à jour des compétences
  - Visibilité dans le système pour les tutorés

#### 3. Tutorés (Tutee)

Ressources accessibles aux tutorés :
- **InscriptionCreneauResource** : Inscription aux créneaux de tutorat
  - Recherche de créneaux par UV, tuteur ou horaire
  - Inscription et désinscription selon les règles établies
  - Historique des séances suivies
  
- **BecomeTutorResource** : Demande pour devenir tuteur
  - Formulaire de candidature
  - Sélection des UVs maîtrisées
  - Suivi de la demande

### Widgets

L'application propose plusieurs widgets pour simplifier l'accès aux informations :
- **TutorCreneauxTableWidget** : Tableau des créneaux pour les tuteurs
  - Affiche les prochains créneaux avec tutorés inscrits
  - Détails sur les UVs demandées
  - Mise en évidence des créneaux du jour
  
- **TuteeCreneauxWidget** : Affichage des créneaux pour les tutorés
  - Liste des séances à venir
  - Rappels et notifications
  - Liens rapides pour la désinscription
  
- **AdminWidget** : Tableau de bord administratif
  - Statistiques d'utilisation
  - Alertes sur les événements importants
  - Vue synthétique de l'activité

## Flux de fonctionnement

### Configuration initiale

1. Un administrateur configure les semestres, semaines et paramètres.
   - Création d'un nouveau semestre (ex: "A25") avec dates de début/fin
   - Définition des semaines avec leurs spécificités (examens, vacances)
   - Configuration des paramètres d'inscription dans `SettingsPage`

### Cycle de réservation des créneaux

2. Les tuteurs employés et non-employés peuvent réserver des créneaux selon des règles de priorité.
   - Les tuteurs employés ont accès aux réservations en début de semaine (ex: lundi à 16h)
   - Les tuteurs non-employés peuvent réserver plus tard (ex: vendredi à 16h)
   - Système de double tuteur possible pour certains créneaux
   - La plateforme vérifie les conflits d'horaire et de salle

### Cycle d'inscription des tutorés

3. Les tutorés peuvent s'inscrire aux créneaux disponibles.
   - Inscription à partir d'un jour/heure défini dans les paramètres
   - Sélection des UVs pour lesquelles ils souhaitent du tutorat
   - Système de recherche par tuteur, UV ou horaire
   - Possibilité d'annulation selon règles définies (délai minimum)

### Déroulement et suivi des séances

4. Les tuteurs dispensent les sessions et remplissent les retours (feedback).
   - Vérification des présences
   - Indication des UVs effectivement traitées
   - Commentaires sur le déroulement
   - Rapport de problèmes éventuels

### Gestion administrative

5. La comptabilité est gérée automatiquement en fonction des sessions effectuées.
   - Comptabilisation des heures par tuteur
   - Calcul de la rémunération
   - Gestion des validations administratives
   - Génération de rapports pour le service financier

## Base de données

### Schéma détaillé

#### Modèle Entité-Relation

Le schéma de la base de données s'articule autour de plusieurs entités principales :

1. **Users** : Utilisateurs du système (tuteurs et tutorés)
2. **Semestres** : Périodes académiques
3. **Semaines** : Sous-divisions des semestres
4. **Salles** : Lieux physiques des séances
5. **Créneaux** : Plages horaires des séances de tutorat
6. **Inscriptions** : Liens entre tutorés et créneaux
7. **UVs** : Unités de valeur/matières enseignées

#### Diagramme des relations clés

```
User (Tutor) 1──n Creneaux n──1 Salle
                  │
                  │
                  n
                  │
User (Tutee) 1────n Inscription

User (Tutor) n────m UV (via tutor_propose)
```

### Tables principales

#### users
- `id` : Identifiant unique
- `email` : Email de l'utilisateur (unique)
- `firstName` : Prénom
- `lastName` : Nom de famille
- `role` : Rôle de l'utilisateur (enum des valeurs définies dans `App\Enums\Roles`)
- `languages` : Langues maîtrisées (JSON)
- `rgpd_accepted_at` : Date d'acceptation RGPD
- Relations:
  - `proposedUvs()`: UVs proposées par un tuteur
  - `heuresSupplementaires()`: Heures supplémentaires du tuteur
  - `comptabilites()`: Entrées de comptabilité associées
  - `becomeTutorRequest()`: Demande pour devenir tuteur

**Implémentation du modèle User :**
```php
class User extends Authenticatable implements HasName
{
    use HasFactory;

    protected $fillable = ['email', 'firstName', 'lastName', 'role', 'languages', 'rgpd_accepted_at'];

    protected $casts = [
        'languages' => 'array',
    ];

    public function getFilamentName(): string
    {
        return ($this->firstName." ".$this->lastName);
    }

    public function proposedUvs()
    {
        return $this->belongsToMany(UV::class, 'tutor_propose', 'fk_user', 'fk_code');
    }     

    public function heuresSupplementaires()
    {
        return $this->hasMany(HeuresSupplementaires::class);
    }    

    public function comptabilites()
    {
        return $this->hasMany(Comptabilite::class, 'fk_user');
    }

    public function becomeTutorRequest()
    {
        return $this->hasOne(BecomeTutor::class, 'fk_user');
    }
}
```

#### semestres
- `code` : Code du semestre (ex: "A25" pour Automne 2025), clé primaire
- `is_active` : Indicateur si le semestre est actif
- `debut` et `fin` : Dates de début et fin du semestre
- `debut_medians` et `fin_medians` : Période des examens médians
- `debut_finaux` et `fin_finaux` : Période des examens finaux
- Relations:
  - `semaines()`: Semaines associées au semestre

**Implémentation de la migration :**
```php
Schema::create('semestres', function (Blueprint $table) {
    $table->string('code', 3)->primary(); // ex: "A25"
    $table->boolean('is_active')->default(false);
    $table->date('debut');
    $table->date('fin');
    $table->date('debut_medians')->nullable();
    $table->date('fin_medians')->nullable();
    $table->date('debut_finaux')->nullable();
    $table->date('fin_finaux')->nullable();
    $table->timestamps();
});
```

#### semaines
- `id` : Identifiant unique
- `numero` : Numéro de la semaine dans le semestre
- `fk_semestre` : Lien vers le semestre parent
- `date_debut` et `date_fin` : Dates de début et fin de la semaine
- Relations:
  - `semestre()`: Semestre auquel appartient la semaine
  - `creneaux()`: Créneaux planifiés durant cette semaine

**Implémentation de la migration :**
```php
Schema::create('semaines', function (Blueprint $table) {
    $table->id();
    $table->integer('numero');
    $table->string('fk_semestre', 3);
    $table->date('date_debut');
    $table->date('date_fin');
    $table->foreign('fk_semestre')->references('code')->on('semestres');
    $table->timestamps();
});
```

#### creneaux
- `id` : Identifiant unique
- `tutor1_id` et `tutor2_id` : Références aux tuteurs assignés (nullable)
- `tutor1_compted` et `tutor2_compted` : Indicateurs de comptabilisation pour la paie
- `fk_semaine` : Semaine du créneau
- `fk_salle` : Salle attribuée
- `start` et `end` : Horaires de début et fin
- Relations:
  - `tutor1()` et `tutor2()`: Tuteurs assignés
  - `semaine()`: Semaine associée
  - `salle()`: Salle associée
  - `inscriptions()`: Inscriptions de tutorés à ce créneau

**Implémentation de la migration :**
```php
Schema::create('creneaux', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tutor1_id')->nullable()->constrained('users')->onDelete('cascade');
    $table->foreignId('tutor2_id')->nullable()->constrained('users')->onDelete('cascade');
    $table->boolean('tutor1_compted')->nullable(); // null = non traité, true/false sinon
    $table->boolean('tutor2_compted')->nullable();
    $table->foreignId('fk_semaine')->constrained('semaines', 'id')->onDelete('cascade');
    $table->foreignId('fk_salle')->constrained('salles', 'numero');
    $table->dateTime('start');
    $table->dateTime('end');
    $table->timestamps();
});
```

#### inscription
- `id` : Identifiant unique
- `tutee_id` : Référence au tutoré inscrit
- `creneau_id` : Référence au créneau choisi
- `enseignements_souhaites` : Liste des UVs demandées (JSON)
- Relations:
  - `tutee()`: Tutoré inscrit
  - `creneau()`: Créneau associé

**Implémentation de la migration :**
```php
Schema::create('inscription', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tutee_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('creneau_id')->constrained('creneaux')->onDelete('cascade');
    $table->json('enseignements_souhaites');
    $table->timestamps();
});
```

#### uvs
- `code` : Code de l'UV (ex: "MT11"), clé primaire
- `name` : Nom complet de l'UV
- Relations:
  - `tutors()`: Tuteurs proposant cette UV via la table pivot `tutor_propose`

**Implémentation de la migration :**
```php
Schema::create('uvs', function (Blueprint $table) {
    $table->string('code', 10)->primary();
    $table->string('name', 100);
    $table->timestamps();
});

Schema::create('tutor_propose', function (Blueprint $table) {
    $table->foreignId('fk_user')->constrained('users')->onDelete('cascade');
    $table->string('fk_code', 10);
    $table->foreign('fk_code')->references('code')->on('uvs')->onDelete('cascade');
    $table->primary(['fk_user', 'fk_code']);
});
```

#### salles
- `numero` : Numéro de la salle, clé primaire
- `capacity` : Capacité de la salle
- Relations:
  - `creneaux()`: Créneaux planifiés dans cette salle
  - `dispoSalles()`: Disponibilités de la salle

**Implémentation de la migration :**
```php
Schema::create('salles', function (Blueprint $table) {
    $table->id('numero');
    $table->integer('capacity')->default(20);
    $table->timestamps();
});
```

#### feedback
- `id` : Identifiant unique
- `creneau_id` : Référence au créneau concerné
- `content` : Contenu du feedback
- Relations:
  - `creneau()`: Créneau associé

**Implémentation de la migration :**
```php
Schema::create('feedback', function (Blueprint $table) {
    $table->id();
    $table->foreignId('creneau_id')->constrained('creneaux')->onDelete('cascade');
    $table->text('content');
    $table->timestamps();
});
```

### Indexation et optimisation

Pour optimiser les requêtes fréquentes, plusieurs index sont créés :

```php
$table->index('role'); // Pour filtrer les utilisateurs par rôle
$table->index('start'); // Pour les recherches par date/heure
$table->index(['tutor1_id', 'tutor2_id']); // Pour les requêtes de créneaux par tuteur
$table->index('fk_semaine'); // Pour filtrer par semaine
```

### Gestion des transactions

Pour les opérations critiques comme la création de créneaux ou l'inscription, l'application utilise des transactions pour garantir l'intégrité des données :

```php
DB::transaction(function () use ($data) {
    // Création du créneau
    $creneau = Creneaux::create([
        'tutor1_id' => Auth::id(),
        'fk_semaine' => $data['semaine'],
        'fk_salle' => $data['salle'],
        'start' => $start,
        'end' => $end,
    ]);
    
    // Autres opérations dépendantes
});
```

## Fonctionnalités spéciales

### Système de paramètres

L'application utilise un système de paramètres stockés dans un fichier JSON (`settings.json`) qui définit notamment :

- Les jours et heures d'ouverture des inscriptions pour chaque type d'utilisateur:
  ```json
  {
    "employedTutorRegistrationDay": "monday",
    "employedTutorRegistrationTime": "16:00",
    "tutorRegistrationDay": "friday",
    "tutorRegistrationTime": "16:00",
    "tuteeRegistrationDay": "saturday",
    "tuteeRegistrationTime": "10:00"
  }
  ```

- Les règles de délai d'annulation, avec deux options:
  1. Utilisation de "la veille" comme règle simple (`useOneDayBeforeCancellation: true`)
  2. Définition d'un jour et heure spécifique (`minTimeCancellationDay` et `minTimeCancellationTime`)

Ces paramètres sont gérés via l'interface `SettingsPage` et sont stockés dans un fichier JSON pour une persistance simple.

#### Implémentation technique

La classe `SettingsPage` gère ces paramètres avec des méthodes dédiées :

```php
protected function loadSettings(): void
{
    if (Storage::exists('settings.json')) {
        $this->settings = json_decode(Storage::get('settings.json'), true) ?: $this->settings;
    }
}

public function saveSettings(): void
{
    $data = $this->form->getState();
    
    foreach ($data as $key => $value) {
        $this->settings[$key] = $value;
    }
    
    // Si on utilise "la veille", on vide les champs minTimeCancellation
    if ($data['useOneDayBeforeCancellation']) {
        $this->settings['minTimeCancellationDay'] = null;
        $this->settings['minTimeCancellationTime'] = null;
    }
    
    Storage::put('settings.json', json_encode($this->settings));
    
    Notification::make()
        ->title(__('resources.pages.settings.notifications.settings_saved_title'))
        ->success()
        ->send();
}
```

#### Utilisation des paramètres

Les paramètres sont utilisés dans tout le système, par exemple pour déterminer si un tuteur peut voir les créneaux de la semaine suivante :

```php
protected static function shouldShowNextWeek(): bool
{
    $user = Auth::user();
    $settings = self::getRegistrationSettings();
    $now = Carbon::now();
    
    if ($user->role === Roles::Tutor->value) {
        $day = $settings['tutorRegistrationDay'] ?? 'friday';
        $time = $settings['tutorRegistrationTime'] ?? '16:00';
    } else {
        $day = $settings['employedTutorRegistrationDay'] ?? 'monday';
        $time = $settings['employedTutorRegistrationTime'] ?? '16:00';
    }
    
    $dayMap = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
    ];
    
    $dayNumber = $dayMap[strtolower($day)] ?? 1;
    
    $registrationDate = Carbon::now()->startOfWeek()->addDays($dayNumber);
    
    $timeParts = explode(':', $time);
    $registrationDate->hour(intval($timeParts[0] ?? 0));
    $registrationDate->minute(intval($timeParts[1] ?? 0));
    $registrationDate->second(0);
    
    // Si on est après la date/heure d'inscription en fct du role, montrer la semaine suivante aussi
    return $now->greaterThanOrEqualTo($registrationDate);
}
```

### Gestion multilingue

L'application supporte le français et l'anglais via le plugin FilamentLanguageSwitch, permettant aux utilisateurs de changer la langue de l'interface.

Configuration dans `AdminPanelProvider.php`:
```php
LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
    $switch
        ->locales(['fr', 'en'])
        ->displayLocale('fr')
        ->labels([
            'fr' => 'Français',
            'en' => 'English',
        ])
        ->renderHook('panels::topbar.end');
});
```

Les traductions sont stockées dans les fichiers de ressources linguistiques de Laravel.

#### Structure des fichiers de traduction

```
resources/
└── lang/
    ├── en/
    │   ├── auth.php
    │   ├── pagination.php
    │   ├── passwords.php
    │   ├── resources.php     # Traductions spécifiques à l'application
    │   └── validation.php
    └── fr/
        ├── auth.php
        ├── pagination.php
        ├── passwords.php
        ├── resources.php     # Traductions spécifiques à l'application
        └── validation.php
```

Exemple de fichier de traduction `resources.php` :

```php
// resources/lang/fr/resources.php
return [
    'common' => [
        'fields' => [
            'jour_et_horaire' => 'Jour et horaire',
            'salle' => 'Salle',
            'semaine' => 'Semaine',
            'tuteur1' => 'Tuteur 1',
            'tuteur2' => 'Tuteur 2',
            'uvs_proposees' => 'UVs proposées',
        ],
        'format' => [
            'semaine_numero' => 'Semaine :number',
        ],
        'placeholders' => [
            'none' => 'Aucun',
        ],
    ],
    'creneau' => [
        'navigation_label' => 'Shotgun Créneaux',
        'label' => 'Créneau',
        'plural_label' => 'Créneaux',
    ],
    // ...
];
```

#### Utilisation des traductions

Dans le code, les traductions sont utilisées via la fonction `__()` :

```php
public static function getNavigationLabel(): string
{
    return __('resources.creneau.navigation_label');
}

public static function getModelLabel(): string
{
    return __('resources.creneau.label');
}
```

### Système de calendrier

La gestion du calendrier permet de définir des périodes spéciales via `CalendarManager` :
- Création d'exceptions pour certaines dates (jours fériés, événements)
- Visualisation du calendrier complet du semestre
- Modification de la disponibilité des salles pour des jours spécifiques
- Personnalisation des plages horaires disponibles

Le système s'appuie sur la table `calendar_overrides` pour stocker les exceptions aux règles générales.

#### Structure de la table des exceptions

```php
Schema::create('calendar_overrides', function (Blueprint $table) {
    $table->id();
    $table->date('date');
    $table->enum('type', ['holiday', 'special_day', 'room_unavailable']);
    $table->string('title');
    $table->text('description')->nullable();
    $table->foreignId('salle_id')->nullable()->constrained('salles', 'numero');
    $table->timestamps();
});
```

#### Interface de gestion du calendrier

La page `CalendarManager` offre une interface visuelle pour gérer ces exceptions :

```php
public function getFormSchema(): array
{
    return [
        DatePicker::make('date')
            ->label(__('resources.pages.calendar.date'))
            ->required(),
        Select::make('type')
            ->options([
                'holiday' => __('resources.pages.calendar.types.holiday'),
                'special_day' => __('resources.pages.calendar.types.special_day'),
                'room_unavailable' => __('resources.pages.calendar.types.room_unavailable'),
            ])
            ->reactive()
            ->required(),
        TextInput::make('title')
            ->label(__('resources.pages.calendar.title'))
            ->required(),
        Textarea::make('description')
            ->label(__('resources.pages.calendar.description')),
        Select::make('salle_id')
            ->label(__('resources.pages.calendar.room'))
            ->options(Salle::pluck('numero', 'numero'))
            ->visible(fn (callable $get) => $get('type') === 'room_unavailable'),
    ];
}
```

#### Utilisation des exceptions dans le système

Avant de créer un créneau, le système vérifie les exceptions :

```php
public static function checkAvailability($date, $salle_id)
{
    // Vérifier si la date est un jour férié ou spécial
    $holiday = CalendarOverride::where('date', $date->format('Y-m-d'))
        ->whereIn('type', ['holiday', 'special_day'])
        ->first();
        
    if ($holiday) {
        return [
            'available' => false,
            'reason' => $holiday->title,
        ];
    }
    
    // Vérifier si la salle est indisponible ce jour
    $roomUnavailable = CalendarOverride::where('date', $date->format('Y-m-d'))
        ->where('type', 'room_unavailable')
        ->where('salle_id', $salle_id)
        ->first();
        
    if ($roomUnavailable) {
        return [
            'available' => false,
            'reason' => 'Salle indisponible: ' . $roomUnavailable->title,
        ];
    }
    
    return ['available' => true];
}
```

### Protection RGPD

L'application inclut un middleware `EnsureRgpdAccepted` qui vérifie que l'utilisateur a accepté les conditions RGPD avant d'accéder au système.

Les dates d'acceptation sont stockées dans le champ `rgpd_accepted_at` de la table `users`.

#### Implémentation du middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureRgpdAccepted
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && !Auth::user()->rgpd_accepted_at && !$request->routeIs('rgpd.*')) {
            return redirect()->route('rgpd.accept');
        }

        return $next($request);
    }
}
```

#### Enregistrement dans AdminPanelProvider.php

```php
->middleware([
    // Autres middlewares...
    EnsureRgpdAccepted::class,
])
```

#### Page d'acceptation RGPD

Une page dédiée permet aux utilisateurs d'accepter les conditions :

```php
public function acceptRgpd()
{
    $user = Auth::user();
    $user->rgpd_accepted_at = now();
    $user->save();
    
    return redirect()->intended(config('filament.home_url'));
}
```

## Exemples d'utilisation typique

### Workflow Administrateur

#### 1. Création d'un nouveau semestre

```php
// Dans AdminSemestreResource.php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            TextInput::make('code')
                ->label(__('resources.semestre.fields.code'))
                ->required()
                ->maxLength(3)
                ->placeholder('A25')
                ->helperText(__('resources.semestre.helpers.code')),
            DatePicker::make('debut')
                ->label(__('resources.semestre.fields.debut'))
                ->required(),
            DatePicker::make('fin')
                ->label(__('resources.semestre.fields.fin'))
                ->required()
                ->after('debut'),
            DatePicker::make('debut_medians')
                ->label(__('resources.semestre.fields.debut_medians')),
            DatePicker::make('fin_medians')
                ->label(__('resources.semestre.fields.fin_medians'))
                ->after('debut_medians'),
            DatePicker::make('debut_finaux')
                ->label(__('resources.semestre.fields.debut_finaux')),
            DatePicker::make('fin_finaux')
                ->label(__('resources.semestre.fields.fin_finaux')),
            Toggle::make('is_active')
                ->label(__('resources.semestre.fields.is_active'))
                ->default(false)
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set, $record) {
                    if ($state && $record) {
                        // Désactiver les autres semestres
                        Semestre::where('code', '!=', $record->code)
                            ->update(['is_active' => false]);
                    }
                }),
        ]);
}
```

Exemple de création d'un semestre :
```
Semestre A25 : 1er septembre 2025 au 15 janvier 2026
Médians : 20 octobre 2025 au 25 octobre 2025
Finaux : 5 janvier 2026 au 15 janvier 2026
```

#### 2. Configuration des paramètres d'inscription

Interface utilisée dans `SettingsPage.php` :

```php
protected function getFormSchema(): array
{
    return [
        Section::make(__('resources.pages.settings.sections.main'))
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Section::make(__('resources.pages.settings.sections.employed_tutor_registration'))
                            ->schema([
                                Select::make('employedTutorRegistrationDay')
                                    ->label(__('resources.pages.settings.fields.day'))
                                    ->options($this->getDays())
                                    ->required(),
                                TimePicker::make('employedTutorRegistrationTime')
                                    ->label(__('resources.pages.settings.fields.time'))
                                    ->seconds(false)
                                    ->required(),
                            ]),
                        // Autres sections similaires pour tutors et tutees
                    ]),
            ])
    ];
}
```

Valeurs typiques configurées :
```
Tuteurs employés : lundi 16h00
Tuteurs : vendredi 16h00
Tutorés : samedi 10h00
Délai d'annulation : la veille
```

#### 3. Définition des salles et capacités

Interface de gestion des salles dans `SalleResource.php` :

```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            TextInput::make('numero')
                ->label(__('resources.salle.fields.numero'))
                ->required()
                ->maxLength(10),
            TextInput::make('capacity')
                ->label(__('resources.salle.fields.capacity'))
                ->required()
                ->numeric()
                ->default(20)
                ->minValue(1)
                ->maxValue(100),
        ]);
}
```

Exemple de configuration de salles :
```
Salle A101 : 20 places
Salle B202 : 15 places
```

### Workflow Tuteur

#### 1. Réservation de créneaux

Logique de vérification d'accès aux créneaux :

```php
public static function table(Table $table): Table
{
    $userId = Auth::id();
    $showNextWeek = self::shouldShowNextWeek();
    
    $query = Creneaux::query()
        ->with([
            'tutor1.proposedUvs:code,code', 
            'tutor2.proposedUvs:code,code',
            'semaine'
        ])
        ->orderBy('start');
    
    $currentWeek = Semaine::where('date_debut', '<=', Carbon::now())
        ->where('date_fin', '>=', Carbon::now())
        ->first();
    
    if ($currentWeek) {
        $nextWeek = Semaine::where('numero', $currentWeek->numero + 1)
            ->where('fk_semestre', $currentWeek->fk_semestre)
            ->first();
        
        if ($showNextWeek && $nextWeek) {
            $query->whereIn('fk_semaine', [$currentWeek->id, $nextWeek->id]);
        } else {
            $query->where('fk_semaine', $currentWeek->id);
        }
    }

    // Suite de la configuration de la table...
}
```

Processus de réservation d'un créneau :
```php
public function createCreneau($data)
{
    // Vérification des conflits
    $existingCreneau = Creneaux::where('tutor1_id', Auth::id())
        ->where('fk_semaine', $data['semaine'])
        ->where(function ($query) use ($data) {
            $start = Carbon::parse($data['date'] . ' ' . $data['start_time']);
            $end = Carbon::parse($data['date'] . ' ' . $data['end_time']);
            
            $query->where(function ($q) use ($start, $end) {
                $q->where('start', '<=', $start)
                  ->where('end', '>', $start);
            })->orWhere(function ($q) use ($start, $end) {
                $q->where('start', '<', $end)
                  ->where('end', '>=', $end);
            });
        })
        ->first();
    
    if ($existingCreneau) {
        Notification::make()
            ->title(__('resources.creneau.notifications.conflict'))
            ->danger()
            ->send();
        return;
    }
    
    // Création du créneau
    $start = Carbon::parse($data['date'] . ' ' . $data['start_time']);
    $end = Carbon::parse($data['date'] . ' ' . $data['end_time']);
    
    Creneaux::create([
        'tutor1_id' => Auth::id(),
        'fk_semaine' => $data['semaine'],
        'fk_salle' => $data['salle'],
        'start' => $start,
        'end' => $end,
    ]);
    
    Notification::make()
        ->title(__('resources.creneau.notifications.created'))
        ->success()
        ->send();
}
```

#### 2. Gestion des UVs proposées

Interface dans `TutorManageUvs.php` :

```php
public function getFormSchema(): array
{
    return [
        CheckboxList::make('selected_uvs')
            ->label(__('resources.pages.tutor_manage_uvs.fields.uvs'))
            ->options(function () {
                return UV::orderBy('code')
                    ->pluck('name', 'code')
                    ->map(function ($name, $code) {
                        return $code . ' - ' . $name;
                    });
            })
            ->columns(3)
            ->default(function () {
                return Auth::user()->proposedUvs->pluck('code')->toArray();
            }),
    ];
}

public function save()
{
    $user = Auth::user();
    $selectedUvs = $this->selected_uvs;
    
    // Supprimer toutes les associations existantes
    DB::table('tutor_propose')
        ->where('fk_user', $user->id)
        ->delete();
    
    // Créer les nouvelles associations
    foreach ($selectedUvs as $uvCode) {
        DB::table('tutor_propose')->insert([
            'fk_user' => $user->id,
            'fk_code' => $uvCode,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    Notification::make()
        ->title(__('resources.pages.tutor_manage_uvs.notifications.saved'))
        ->success()
        ->send();
}
```

Exemple de sélection d'UVs par un tuteur :
```
MT11 - Mathématiques
MA11 - Algèbre linéaire
LC01 - Anglais
```

#### 3. Consultation des créneaux avec inscrits

Widget tuteur montrant les créneaux avec inscriptions :

```php
class TutorCreneauxTableWidget extends BaseWidget
{
    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = Auth::user();

        return Creneaux::query()
            ->with(['tutor1.proposedUvs', 'tutor2.proposedUvs', 'salle', 'semaine', 'inscriptions'])
            ->where('end', '>=', now())
            ->where(function ($query) use ($user) {
                $query->where('tutor1_id', $user->id)
                      ->orWhere('tutor2_id', $user->id);
            })
            ->whereHas('inscriptions')
            ->orderBy('start');
    }
    
    // Colonnes et configuration de la table...
}
```

### Workflow Tutoré

#### 1. Inscription à un créneau

Processus d'inscription dans `InscriptionCreneauResource` :

```php
public function create($data)
{
    $user = Auth::user();
    $creneau = Creneaux::findOrFail($data['creneau_id']);
    
    // Vérifier si l'utilisateur est déjà inscrit
    $existingInscription = Inscription::where('tutee_id', $user->id)
        ->where('creneau_id', $creneau->id)
        ->first();
    
    if ($existingInscription) {
        Notification::make()
            ->title(__('resources.inscription.notifications.already_registered'))
            ->warning()
            ->send();
        return;
    }
    
    // Vérifier les délais d'annulation
    $canRegister = $this->checkRegistrationTime($creneau);
    if (!$canRegister['can_register']) {
        Notification::make()
            ->title($canRegister['message'])
            ->warning()
            ->send();
        return;
    }
    
    // Créer l'inscription
    Inscription::create([
        'tutee_id' => $user->id,
        'creneau_id' => $creneau->id,
        'enseignements_souhaites' => json_encode($data['enseignements_souhaites']),
    ]);
    
    Notification::make()
        ->title(__('resources.inscription.notifications.registered'))
        ->success()
        ->send();
}
```

Exemple d'inscription :
```
Inscription au créneau de Mardi 14h-16h avec tuteur Jean Dupont
UVs demandées : MT11, MA11
```

#### 2. Vérification des règles d'annulation

Logique de vérification pour les annulations :

```php
protected function checkCancellationTime(Creneaux $creneau): array
{
    $settings = $this->getSettings();
    $now = Carbon::now();
    
    // Si on utilise la règle simple de "la veille"
    if ($settings['useOneDayBeforeCancellation'] ?? false) {
        $limitDate = Carbon::parse($creneau->start)->subDay()->endOfDay();
        
        if ($now->greaterThan($limitDate)) {
            return [
                'can_cancel' => false,
                'message' => __('resources.inscription.messages.too_late_to_cancel_one_day'),
            ];
        }
        
        return ['can_cancel' => true];
    }
    
    // Sinon, utiliser la règle personnalisée
    $day = $settings['minTimeCancellationDay'] ?? 'friday';
    $time = $settings['minTimeCancellationTime'] ?? '16:00';
    
    // Calculer la date limite selon les paramètres
    $dayMap = [
        'sunday' => 0, 'monday' => 1, 'tuesday' => 2,
        'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6,
    ];
    
    $dayNumber = $dayMap[strtolower($day)] ?? 5;
    $timeParts = explode(':', $time);
    
    $limitDate = Carbon::parse($creneau->start)
        ->startOfWeek()
        ->addDays($dayNumber)
        ->setHour(intval($timeParts[0] ?? 16))
        ->setMinute(intval($timeParts[1] ?? 0))
        ->setSecond(0);
    
    if ($now->greaterThan($limitDate)) {
        return [
            'can_cancel' => false,
            'message' => __('resources.inscription.messages.too_late_to_cancel_custom', [
                'day' => __("resources.pages.settings.days.{$day}"),
                'time' => $time,
            ]),
        ];
    }
    
    return ['can_cancel' => true];
}
```

### Workflow de suivi

#### 1. Visualisation des inscriptions

Widget tuteur montrant les UVs demandées :

```php
TextColumn::make('id')
    ->label(__('resources.widgets.tutor_creneaux.columns.requested_courses'))
    ->formatStateUsing(function ($state, Creneaux $creneau) {
        $uvs = $creneau->inscriptions
            ->flatMap(fn($inscription) => json_decode($inscription->enseignements_souhaites ?? '[]'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $uvs->implode(', ') ?: __('resources.common.placeholders.none');
    })
    ->icon('heroicon-o-academic-cap')
    ->color('primary'),
```

#### 2. Création de feedback après séance

Interface dans `FeedbackResource` :

```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Select::make('creneau_id')
                ->label(__('resources.feedback.fields.creneau'))
                ->options(function () {
                    $user = Auth::user();
                    return Creneaux::where(function ($query) use ($user) {
                            $query->where('tutor1_id', $user->id)
                                  ->orWhere('tutor2_id', $user->id);
                        })
                        ->where('end', '<', now())
                        ->with(['semaine'])
                        ->orderBy('start', 'desc')
                        ->get()
                        ->mapWithKeys(function ($creneau) {
                            $date = Carbon::parse($creneau->start)->format('d/m/Y H:i');
                            return [$creneau->id => "Créneau du {$date} (Semaine {$creneau->semaine->numero})"];
                        });
                })
                ->required()
                ->searchable(),
            Textarea::make('content')
                ->label(__('resources.feedback.fields.content'))
                ->required()
                ->columnSpan('full'),
            CheckboxList::make('present_students')
                ->label(__('resources.feedback.fields.present_students'))
                ->options(function (callable $get) {
                    $creneauId = $get('creneau_id');
                    if (!$creneauId) return [];
                    
                    return Inscription::where('creneau_id', $creneauId)
                        ->with('tutee')
                        ->get()
                        ->mapWithKeys(function ($inscription) {
                            $tutee = $inscription->tutee;
                            return [$inscription->tutee_id => "{$tutee->firstName} {$tutee->lastName}"];
                        });
                })
                ->columns(2),
        ]);
}
```

#### 3. Gestion de la comptabilité

Traitement automatique dans `ComptabiliteResource` :

```php
public function processCreneaux()
{
    $unprocessedCreneaux = Creneaux::where(function ($query) {
            $query->whereNull('tutor1_compted')
                  ->orWhereNull('tutor2_compted');
        })
        ->where('end', '<', now())
        ->get();
    
    $count = 0;
    
    foreach ($unprocessedCreneaux as $creneau) {
        $duration = Carbon::parse($creneau->start)->diffInHours(Carbon::parse($creneau->end));
        
        if ($creneau->tutor1_id && $creneau->tutor1_compted === null) {
            Comptabilite::create([
                'fk_user' => $creneau->tutor1_id,
                'fk_creneau' => $creneau->id,
                'date' => $creneau->start,
                'heures' => $duration,
            ]);
            
            $creneau->tutor1_compted = true;
            $count++;
        }
        
        if ($creneau->tutor2_id && $creneau->tutor2_compted === null) {
            Comptabilite::create([
                'fk_user' => $creneau->tutor2_id,
                'fk_creneau' => $creneau->id,
                'date' => $creneau->start,
                'heures' => $duration,
            ]);
            
            $creneau->tutor2_compted = true;
            $count++;
        }
        
        $creneau->save();
    }
    
    Notification::make()
        ->title(__('resources.comptabilite.notifications.processed', ['count' => $count]))
        ->success()
        ->send();
}
```

Exemples de statistiques générées :
```
Jean Dupont : 8h effectuées cette semaine
Total du mois : 24h
Total des heures supplémentaires : 2h
```

### Cycle complet d'utilisation

1. L'administrateur configure un semestre A25 du 1er septembre 2025 au 15 janvier 2026
2. Il définit les semaines 1 à 16 avec leurs dates spécifiques
3. Les paramètres sont configurés pour ouvrir les inscriptions :
   - Tuteurs employés : lundi 16h00
   - Tuteurs : vendredi 16h00
   - Tutorés : samedi 10h00
4. Le lundi 12 septembre 2025 à 16h00, les tuteurs employés peuvent réserver des créneaux
5. Marie Dupont (tuteur employé) réserve le créneau du mardi 13 septembre de 14h à 16h en salle A101
6. Le vendredi 16 septembre à 16h00, les tuteurs non-employés peuvent réserver des créneaux
7. Paul Martin (tuteur) réserve le créneau du jeudi 15 septembre de 10h à 12h en salle B202
8. Le samedi 17 septembre à 10h00, les tutorés peuvent s'inscrire
9. Sophie Petit (tutorée) s'inscrit au créneau de Marie Dupont pour les UVs MT11 et MA11
10. Le mardi 13 septembre à 14h, Marie Dupont dispense sa séance de tutorat
11. Après la séance, elle remplit un feedback indiquant que Sophie était présente et que les intégrales multiples ont posé problème
12. L'administrateur traite la comptabilité, ajoutant 2 heures au compteur de Marie