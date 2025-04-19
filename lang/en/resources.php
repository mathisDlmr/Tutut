<?php

return [
    'tuteurs_employes' => [
        'label' => 'Account Management',
        'plural_label' => 'Account Management',
        'fields' => [
            'email' => 'Email Address',
            'role' => 'Role',
        ],
        'roles' => [
            'administrator' => 'Administrator',
            'employed_tutor' => 'Employed Tutor',
            'employed_privileged_tutor' => 'Privileged Tutor',
            'tutor' => 'Tutor',
            'tutee' => 'Tutee',
        ],
        'actions' => [
            'delete' => 'Revoke Role',
            'upgrade' => 'Upgrade',
            'downgrade' => 'Downgrade',
        ],
    ],
];
