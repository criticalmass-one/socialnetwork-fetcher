<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Profile;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
                'attr' => ['data-controller' => 'searchable-select'],
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
            ])
            ->add('profiles', EntityType::class, [
                'class' => Profile::class,
                'multiple' => true,
                'expanded' => false,
                'label' => 'Profile',
                'required' => false,
                'attr' => [
                    'data-controller' => 'searchable-select',
                    'data-searchable-select-placeholder-value' => 'Profil suchen und hinzufügen …',
                ],
                'choice_label' => fn(Profile $p) => sprintf('%s — %s', $p->getNetwork()?->getName() ?? '?', $p->getDisplayName()),
                'query_builder' => fn(EntityRepository $repository) => $repository->createQueryBuilder('p')
                    ->leftJoin('p.network', 'n')
                    ->addSelect('n')
                    ->andWhere('p.deleted = false')
                    ->orderBy('n.name', 'ASC')
                    ->addOrderBy('p.identifier', 'ASC'),
                'help' => 'Profile, die zur Gruppe gehören. Profile, die nicht zum gewählten Client verknüpft sind, werden beim Speichern abgewiesen.',
            ]);

        // --- Öffentliche Seite ---
        $builder
            ->add('publicPageEnabled', CheckboxType::class, [
                'label' => 'Öffentliche Seite aktivieren',
                'required' => false,
                'help' => 'Macht die Gruppe unter /p/{Slug} ohne Login abrufbar. Beim ersten Aktivieren wird automatisch ein Slug erzeugt.',
            ])
            ->add('publicTitle', TextType::class, [
                'label' => 'Öffentlicher Titel',
                'required' => false,
                'help' => 'Überschrift der öffentlichen Seite. Leer = Gruppenname.',
            ])
            ->add('publicDescription', TextareaType::class, [
                'label' => 'Öffentliche Beschreibung',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('showPhotos', CheckboxType::class, [
                'label' => 'Fotos anzeigen',
                'required' => false,
            ])
            ->add('showVideos', CheckboxType::class, [
                'label' => 'Videos anzeigen',
                'required' => false,
            ])
            ->add('showTranscript', CheckboxType::class, [
                'label' => 'Video-Transkription anzeigen',
                'required' => false,
            ])
            ->add('showCaptions', CheckboxType::class, [
                'label' => 'Beitragstext anzeigen',
                'required' => false,
            ])
            ->add('timeWindowDays', IntegerType::class, [
                'label' => 'Zeitfenster (Tage)',
                'required' => false,
                'help' => 'Wie viele Tage zurück angezeigt werden. Leer = alle Beiträge.',
                'attr' => ['min' => 1],
            ])
            ->add('publicPassword', PasswordType::class, [
                'label' => 'Passwort',
                'required' => false,
                'mapped' => false,
                'help' => 'Optionaler Passwortschutz. Leer lassen, um ein vorhandenes Passwort unverändert zu lassen.',
                'attr' => ['autocomplete' => 'new-password'],
            ])
            ->add('removePublicPassword', CheckboxType::class, [
                'label' => 'Passwort entfernen',
                'required' => false,
                'mapped' => false,
                'help' => 'Entfernt einen bestehenden Passwortschutz.',
            ]);
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
