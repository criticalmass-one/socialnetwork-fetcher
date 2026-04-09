<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Network;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NetworkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('identifier', TextType::class, [
                'label' => 'Identifier',
                'help' => 'Eindeutiger technischer Bezeichner (z.B. mastodon, bluesky)',
            ])
            ->add('name', TextType::class, [
                'label' => 'Name',
                'help' => 'Anzeigename des Netzwerks',
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icon',
                'help' => 'FontAwesome-Klasse (z.B. fab fa-mastodon)',
            ])
            ->add('backgroundColor', TextType::class, [
                'label' => 'Hintergrundfarbe',
                'help' => 'CSS-Farbwert (z.B. #6364FF)',
            ])
            ->add('textColor', TextType::class, [
                'label' => 'Textfarbe',
                'help' => 'CSS-Farbwert (z.B. #FFFFFF)',
            ])
            ->add('profileUrlPattern', TextType::class, [
                'label' => 'Profil-URL-Pattern',
                'help' => 'Regulärer Ausdruck zur Validierung von Profil-URLs',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Network::class,
        ]);
    }
}
