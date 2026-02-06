<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Item;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titel',
                'required' => false,
            ])
            ->add('text', TextareaType::class, [
                'label' => 'Text',
                'attr' => ['rows' => 5],
            ])
            ->add('permalink', TextType::class, [
                'label' => 'Permalink',
                'required' => false,
            ])
            ->add('hidden', CheckboxType::class, [
                'label' => 'Versteckt',
                'required' => false,
            ])
            ->add('deleted', CheckboxType::class, [
                'label' => 'Gelöscht',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Item::class,
        ]);
    }
}
