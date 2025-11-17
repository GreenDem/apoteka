<?php

declare(strict_types=1);

namespace Application\Form;

use Laminas\Form\Element\Csrf;
use Laminas\Form\Element\Email as EmailElement;
use Laminas\Form\Element\Password as PasswordElement;
use Laminas\Form\Element\Submit;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilter;

final class LoginForm extends Form
{
    public function __construct()
    {
        parent::__construct('login-form');

        $this->add(new EmailElement('email', [
            'label' => 'Email',
        ]));

        $this->add(new PasswordElement('password', [
            'label' => 'Mot de passe',
        ]));

        $this->add(new Csrf('csrf'));

        $this->add(new Submit('submit', [
            'value' => 'Connexion',
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
            'filters' => [
                ['name' => 'StringTrim'],
            ],
        ]);

        return $filter;
    }
}