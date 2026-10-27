<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

/**
 * Korisnik sam menja svoj profil. Email, rola i pozicija ostaju adminu.
 */
class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Ime'])
            ->add('lastName', TextType::class, ['label' => 'Prezime'])
            ->add('phone', TextType::class, ['label' => 'Telefon', 'required' => false])
            ->add('address', TextType::class, ['label' => 'Adresa', 'required' => false])
            ->add('city', TextType::class, ['label' => 'Grad', 'required' => false])
            ->add('postalCode', TextType::class, ['label' => 'Poštanski broj', 'required' => false])
            ->add('avatarFile', VichImageType::class, [
                'label' => 'Avatar',
                'required' => false,
                'allow_delete' => true,
                'delete_label' => 'Ukloni avatar',
                'download_uri' => false,
                'image_uri' => false,
                'help' => 'JPG, PNG ili WebP, do 2 MB.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
