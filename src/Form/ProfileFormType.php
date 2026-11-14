<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (['nom' => 'Nom', 'prenom' => 'Prénom', 'adresse' => 'Adresse', 'ville' => 'Ville', 'codePostal' => 'Code postal'] as $field => $label) {
            $builder->add($field, TextType::class, ['label' => $label]);
        }

        // Cette option est déterminée côté serveur, jamais à partir de la requête.
        if ($options['can_manage_roles']) {
            $builder->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => ['Utilisateur' => 'ROLE_USER', 'Administrateur' => 'ROLE_ADMIN'],
                'multiple' => true,
                'expanded' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'can_manage_roles' => false]);
        $resolver->setAllowedTypes('can_manage_roles', 'bool');
    }
}
