<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Network;
use App\Entity\Profile;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['is_new']) {
            $builder->add('id', IntegerType::class, [
                'label' => 'ID',
                'help' => 'Eindeutige numerische ID für dieses Profil',
            ]);
        }

        $builder
            ->add('network', EntityType::class, [
                'class' => Network::class,
                'choice_label' => 'name',
                'placeholder' => 'Netzwerk wählen...',
                'label' => 'Netzwerk',
            ])
            ->add('identifier', TextType::class, [
                'label' => 'Identifier',
                'help' => 'URL oder Benutzername im Netzwerk',
            ])
            ->add('autoPublish', CheckboxType::class, [
                'label' => 'Auto-Publish',
                'required' => false,
            ])
            ->add('autoFetch', CheckboxType::class, [
                'label' => 'Auto-Fetch',
                'required' => false,
            ])
            ->add('additionalData', TextareaType::class, [
                'label' => 'Zusätzliche Daten (JSON)',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
        ;

        $builder->get('additionalData')
            ->addModelTransformer(new CallbackTransformer(
                fn (?array $data): string => $data !== null ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '',
                fn (?string $json): ?array => ($json !== null && $json !== '') ? json_decode($json, true) : null,
            ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Profile::class,
            'is_new' => false,
        ]);
    }
}
