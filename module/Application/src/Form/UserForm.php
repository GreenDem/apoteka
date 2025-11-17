<?php

declare(strict_types=1);

namespace Application\Form;

use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Db\Adapter\AdapterInterface;

class UserForm extends Form implements InputFilterProviderInterface
{
    private bool $isEdit = false;
    private AdapterInterface $dbAdapter;

    public function __construct(AdapterInterface $dbAdapter, bool $isEdit = false)
    {
        parent::__construct('user-form');
        
        $this->dbAdapter = $dbAdapter;
        $this->isEdit = $isEdit;
        
        $this->addElements();
    }

    private function addElements(): void
    {
        // Champ ID (caché pour l'édition)
        $this->add([
            'name' => 'id',
            'type' => 'hidden',
        ]);

        // Champ Email
        $this->add([
            'name' => 'email',
            'type' => 'email',
            'options' => [
                'label' => 'Email',
            ],
            'attributes' => [
                'class' => 'form-control',
                'placeholder' => 'exemple@email.com',
            ],
        ]);

        // Champ Mot de passe
        $this->add([
            'name' => 'password',
            'type' => 'password',
            'options' => [
                'label' => 'Mot de passe',
            ],
            'attributes' => [
                'class' => 'form-control',
                'autocomplete' => 'new-password',
            ],
        ]);

        // Champ Confirmation mot de passe
        $this->add([
            'name' => 'password_confirm',
            'type' => 'password',
            'options' => [
                'label' => 'Confirmation du mot de passe',
            ],
            'attributes' => [
                'class' => 'form-control',
                'autocomplete' => 'new-password',
            ],
        ]);

        // Champ Rôles
        $this->add([
            'name' => 'roles',
            'type' => 'select',
            'options' => [
                'label' => 'Rôle',
                'value_options' => [
                    'user' => 'Utilisateur',
                    'admin' => 'Administrateur',
                ],
            ],
            'attributes' => [
                'class' => 'form-control',
            ],
        ]);

        // Protection CSRF
        $this->add([
            'name' => 'csrf',
            'type' => 'csrf',
            'options' => [
                'csrf_options' => [
                    'timeout' => 600, // 10 minutes
                ],
            ],
        ]);

        // Bouton Submit
        $this->add([
            'name' => 'submit',
            'type' => 'submit',
            'attributes' => [
                'value' => $this->isEdit ? 'Mettre à jour' : 'Créer',
                'class' => 'btn btn-primary',
            ],
        ]);
    }

    public function getInputFilterSpecification(): array
    {
        $inputFilter = [];

        // Filtre Email
        $inputFilter['email'] = [
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => 'StripTags'],
            ],
            'validators' => [
                ['name' => 'EmailAddress'],
            ],
        ];



        // Filtre Mot de passe
        $inputFilter['password'] = [
            'required' => ! $this->isEdit, // Optionnel en édition
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => 'StringLength',
                    'options' => [
                        'min' => 12,
                        'max' => 72, // Limite bcrypt/Argon2
                    ],
                ],
                [
                    'name' => 'Regex',
                    'options' => [
                        'pattern' => '/^(?=.*[A-Z])(?=.*[0-9])(?=.*\W).+$/',
                        'messages' => [
                            'regexNotMatch' => 'Le mot de passe doit contenir au moins une majuscule, un chiffre et un caractère spécial.',
                        ],
                    ],
                ],
            ],
        ];

        // Filtre Confirmation mot de passe
        $inputFilter['password_confirm'] = [
            'required' => ! $this->isEdit,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => 'Identical',
                    'options' => [
                        'token' => 'password',
                        'messages' => [
                            'notSame' => 'Les mots de passe ne correspondent pas.',
                        ],
                    ],
                ],
            ],
        ];

        // Filtre Rôles
        $inputFilter['roles'] = [
            'required' => true,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
            'validators' => [
                [
                    'name' => 'InArray',
                    'options' => [
                        'haystack' => ['user', 'admin'],
                        'messages' => [
                            'notInArray' => 'Rôle invalide.',
                        ],
                    ],
                ],
            ],
        ];

        return $inputFilter;
    }
}