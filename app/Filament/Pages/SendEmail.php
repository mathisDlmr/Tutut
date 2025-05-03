<?php

namespace App\Filament\Pages;

use App\Enums\Roles;
use App\Models\User;
use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\{Grid, RichEditor, Select, TextInput};
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendEmail extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static string $view = 'filament.pages.send-email';
    protected static ?string $navigationLabel = 'Envoyer un mail';
    protected static ?string $navigationGroup = 'Gestion';

    public $template;
    public $templateName;
    public $templateOptions = [];
    public $mailTitle;
    public $content;
    public $roles = [];

    public function mount()
    {
        $this->templateOptions = $this->getTemplateOptions();
    }    

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && (Auth::user()->role === Roles::EmployedPrivilegedTutor->value
            || Auth::user()->role === Roles::Administrator->value);
    }   

    protected function getFormSchema(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('template')
                    ->label('Charger un template')
                    ->options(fn () => $this->templateOptions)
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state && Storage::exists("email-templates/{$state}.json")) {
                            $data = json_decode(Storage::get("email-templates/{$state}.json"), true);
                            $set('mailTitle', $data['title'] ?? '');
                            $set('content', $data['content'] ?? '');
                        }
                }),            
            ]),
    
            Select::make('roles')
                ->label('Destinataires')
                ->multiple()
                ->options(collect(Roles::cases())
                    ->mapWithKeys(fn ($role) => [
                        $role->value => match ($role) {
                            Roles::Administrator => 'Administrateur',
                            Roles::EmployedPrivilegedTutor => 'Tuteur privilégié employé',
                            Roles::EmployedTutor => 'Tuteur employé',
                            Roles::Tutor => 'Tuteur',
                            Roles::Tutee => 'Élève',
                        }
                    ])
                    ->toArray()
                )
                ->required(),
    
            TextInput::make('mailTitle')
                ->label('Sujet')
                ->required(),
    
            RichEditor::make('content')
                ->label('Contenu')
                ->required(),
    
            TextInput::make('templateName')
                ->label('Nom du template')
                ->placeholder('Nom pour enregistrer un template')
                ->helperText('Requis uniquement pour l’enregistrement'),
        ];
    }    

    public function previewEmail()
    {
        $this->dispatch('open-modal', id: 'email-preview');
    }    

    public function sendEmail()
    {
        if (empty($this->roles)) {
            Notification::make()
                ->title('Erreur')
                ->body('Veuillez sélectionner au moins un rôle.')
                ->danger()
                ->send();
            return;
        }

        $users = User::whereIn('role', $this->roles)->get();

        foreach ($users as $user) {
            Mail::raw(strip_tags($this->content), function ($message) use ($user) {
                $message->to($user->email)
                        ->subject($this->mailTitle);
            });
        }

        Notification::make()
            ->title('Succès')
            ->body("Email envoyé à {$users->count()} utilisateur(s).")
            ->success()
            ->send();
    }

    public function saveTemplate()
    {
        if (empty($this->templateName)) {
            Notification::make()
                ->title('Erreur')
                ->body('Veuillez entrer un nom pour enregistrer le template.')
                ->danger()
                ->send();
            return;
        }

        $filename = 'email-templates/' . strtolower(str_replace(' ', '_', $this->templateName)) . '.json';

        $templateData = [
            'title' => $this->mailTitle,
            'content' => $this->content,
        ];

        Storage::put($filename, json_encode($templateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Notification::make()
            ->title('Template enregistré')
            ->body("Le template « {$this->templateName} » a été sauvegardé.")
            ->success()
            ->send();

        $this->templateName = null;
        $this->templateOptions = $this->getTemplateOptions();
    }

    public function deleteTemplate()
    {
        if ($this->template && Storage::exists("email-templates/{$this->template}.json")) {
            Storage::delete("email-templates/{$this->template}.json");

            Notification::make()
                ->title('Template supprimé')
                ->body("Le template « {$this->template} » a été supprimé.")
                ->success()
                ->send();

            $this->template = null;
        }
    }

    protected function getTemplateOptions(): array
    {
        $files = Storage::files('email-templates');

        return collect($files)->mapWithKeys(function ($file) {
            $filename = basename($file, '.json');
            return [$filename => ucfirst(str_replace('_', ' ', $filename))];
        })->toArray();
    }

    protected function getRolesOptions(): array
    {
        return collect(Roles::cases())
            ->mapWithKeys(fn ($role) => [$role->value => ucfirst(str_replace('_', ' ', $role->name))])
            ->toArray();
    }
}
