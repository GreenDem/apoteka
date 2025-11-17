<?php

declare(strict_types=1);

namespace Application\Form;

use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Csrf;
use Laminas\Form\Element\Email as EmailElement;
use Laminas\Form\Element\Password as PasswordElement;
use Laminas\Form\Element\Submit;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilter;

final class RegisterForm extends Form
{
    public function __construct()
    {
        parent::__construct('register-form');

        $this->add(new EmailElement('email', [
            'label' => 'Email',
        ]));

        $this->add(new PasswordElement('password', [
            'label' => 'Mot de passe',
        ]));

        $this->add(new PasswordElement('password_confirm', [
            'label' => 'Confirmation',
        ]));

        $this->add(new Checkbox('terms', [
            'label' => 'J’accepte les conditions d’utilisation',
            'use_hidden_element' => false,
        ]));

        $this->add(new Csrf('csrf'));

        $this->add(new Submit('submit', [
            'value' => 'Créer mon compte',
        ]));

        $this->setInputFilter($this->createInputFilter());
    }

    private function createInputFilter(): InputFilter
    {
        $filter = new InputFilter();

        $filter->add([
            'name' => 'email',
            'required' => true,
            'validators' => [
                [
                    'name' => 'EmailAddress',
                    'options' => [
                        'allow' => \Laminas\Validator\Hostname::ALLOW_DNS,
                        'useMxCheck' => false,
                    ],
                ],
            ],
            'filters' => [
                ['name' => 'StringTrim'],
                ['name' => 'StringToLower'],
            ],
        ]);

        $filter->add([
            'name' => 'password',
            'required' => true,
            'validators' => [
                [
                    'name' => 'StringLength',
                    'options' => [
                        'min' => 8,
                        'max' => 255,
                    ],
                ],
            ],
            'filters' => [
                ['name' => 'StringTrim'],
            ],
        ]);

        $filter->add([
            'name' => 'password_confirm',
            'required' => true,
            'validators' => [
                [
                    'name' => 'Identical',
                    'options' => [
                        'token' => 'password',
                        'message' => 'Les mots de passe ne correspondent pas.',
                    ],
                ],
            ],
        ]);

        $filter->add([
            'name' => 'terms',
            'required' => true,
            'validators' => [
                [
                    'name' => 'InArray',
                    'options' => [
                        'haystack' => [true, 'true', 1, '1'],
                        'strict' => false,
                        'messages' => [
                            \Laminas\Validator\InArray::NOT_IN_ARRAY => 'Merci d’accepter les conditions.',
                        ],
                    ],
                ],
            ],
        ]);

        return $filter;
    }
}

