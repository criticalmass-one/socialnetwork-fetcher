<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Client;
use App\Entity\Group;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class GroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'Name',
            'help' => 'Eindeutig pro Client.',
        ]);

        // Client-token users always own their own groups; hide and lock the client field.
        if ($options['lock_client_to'] === null) {
            $builder->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'label' => 'Client',
                'placeholder' => 'Client wählen...',
                'help' => 'Eigentümer-Client der Gruppe. Bestimmt, wer sie über die API sieht.',
                'constraints' => [new NotNull(message: 'Bitte einen Client wählen.')],
            ]);
        }

        $builder
            ->add('description', TextareaType::class, [
                'label' => 'Beschreibung',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('color', ColorType::class, [
                'label' => 'Farbe',
                'required' => false,
                'help' => 'Optional, für die Badge-Darstellung im UI.',
            ]);
        // Mitglieder werden nicht mehr über dieses Formular gepflegt, sondern
        // über den durchsuchbaren Picker auf der Gruppen-Detailseite (und die
        // Profil-Detailseite). Das frühere Multi-Select über alle Profile war
        // bei ~2000 Einträgen unbenutzbar und hat beim Bearbeiten die
        // Mitgliederliste ersetzt.
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Group::class,
            'lock_client_to' => null,
        ]);
        $resolver->setAllowedTypes('lock_client_to', ['null', Client::class]);
    }
}
