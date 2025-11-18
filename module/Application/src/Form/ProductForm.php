<?php

namespace Application\Form;

use Laminas\Form\Form;
use Laminas\Form\Element;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Hydrator\ReflectionHydrator; // Ajout de l'import

final class ProductForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('product-form');

        // Configuration du hydrator pour utiliser ReflectionHydrator
        // Compatible avec Product qui utilise des getters/setters
        $this->setHydrator(new ReflectionHydrator());

        $this->addElements();
    }

    private function addElements(): void
    {
        $this->add(['name' => 'id', 'type' => Element\Hidden::class]);

        $this->add([
            'name' => 'name',
            'type' => Element\Text::class, // Champ texte pour le nom du produit
            'options' => ['label' => 'Nom'],
            'attributes' => ['class' => 'form-control', 'required' => true], // Champ obligatoire
        ]);

        $this->add([
            'name' => 'description',
            'type' => Element\Textarea::class, // Champ textarea pour la description du produit
            'options' => ['label' => 'Description'],
            'attributes' => ['rows' => 3, 'class' => 'form-control'], //Optionnel
        ]);

        $this->add([
            'name' => 'price',
            'type' => Element\Number::class, // Champ nombre pour le prix du produit
            'options' => ['label' => 'Prix (€)'],
            'attributes' => ['step' => '0.01', 'min' => '0', 'class' => 'form-control'], // Pas de décimales négatives
        ]);

        $this->add([
            'name' => 'stock',
            'type' => Element\Number::class,
            'options' => ['label' => 'Stock'],
            'attributes' => ['min' => '0', 'class' => 'form-control'], // Stock minimum de 0
        ]);

        $this->add([
            'name' => 'img',
            'type' => Element\Url::class,
            'options' => ['label' => 'URL de l’image'], // URL de l'image du produit
            'attributes' => ['placeholder' => 'https://', 'class' => 'form-control'], // Placeholder pour l'URL de l'image
        ]);

        // Protection CSRF
        $this->add(new Element\Csrf('csrf'));

        // Bouton de soumission
        $submit = new Element\Submit('submit');
        $submit->setValue('Enregistrer');
        $submit->setAttribute('class', 'btn btn-primary');
        $this->add($submit);
    }


    //SANITISATION ET VALIDATION DES CHAMPS
    public function getInputFilterSpecification(): array
    {
        return [
            'name' => [
                'required' => true, // Champ obligatoire
                'filters' => [['name' => 'StripTags'], ['name' => 'StringTrim']], // Nettoyage des tags et espaces
                'validators' => [['name' => 'StringLength', 'options' => ['min' => 3, 'max' => 100]]], // Longueur minimale de 3 caractères et maximale de 100 caractères
            ],
            'price' => [
                'required' => true, // Champ obligatoire
                'filters' => [['name' => 'ToFloat']], // Conversion en float
                'validators' => [
                    ['name' => 'GreaterThan', 'options' => ['min' => 0, 'inclusive' => true]], // Prix minimum de 0
                ],
            ],
            'stock' => [
                'required' => true, // Champ obligatoire
                'filters' => [['name' => 'ToInt']], // Conversion en int
                'validators' => [
                    ['name' => 'Digits'],
                    ['name' => 'GreaterThan', 'options' => ['min' => 0]], // Stock minimum de 0
                ],
            ],
            'img' => [
                'required' => false,
                'validators' => [
                    ['name' => 'Uri'], // URL valide
                ],
            ],
        ];
    }
}