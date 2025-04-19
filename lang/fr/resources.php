<?php

return [
    'tuteurs_employes' => [
        'label' => 'gestion Comptes',
        'plural_label' => 'Gestion Comptes',
        'fields' => [
            'email' => 'Adresse Email',
            'role' => 'Rôle',
        ],
        'roles' => [
            'administrator' => 'Administrateur',
            'employed_tutor' => 'Tuteur Employé',
            'employed_privileged_tutor' => 'Tuteur Employé Privilégié',
            'tutor' => 'Tuteur',
            'tutee' => 'Tutoré',
        ],
        'actions' => [
            'delete' => 'Supprimer les droits',
            'upgrade' => 'Améliorer',
            'downgrade' => 'Rétrograder',
        ],
    ],
];
