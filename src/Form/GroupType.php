<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Profile;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'help' => 'Eindeutig pro Client.',
            ])
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'label' => 'Client',
                'placeholder' => 'Client wählen...',
                'help' => 'Eigentümer-Client der Gruppe. Bestimmt, wer sie über die API sieht.',
            ])
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
                'attr' => ['size' => 15],
                'choice_label' => fn(Profile $p) => sprintf('%s — %s', $p->getNetwork()?->getName() ?? '?', $p->getDisplayName()),
                'query_builder' => fn(EntityRepository $repository) => $repository->createQueryBuilder('p')
                    ->leftJoin('p.network', 'n')
                    ->addSelect('n')
                    ->andWhere('p.deleted = false')
                    ->orderBy('n.name', 'ASC')
                    ->addOrderBy('p.identifier', 'ASC'),
                'help' => 'Profile, die zur Gruppe gehören. Profile, die nicht zum gewählten Client verknüpft sind, werden beim Speichern abgewiesen.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Group::class,
        ]);
    }
}
