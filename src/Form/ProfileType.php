<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Network;
use App\Entity\Profile;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('network', EntityType::class, [
                'class' => Network::class,
                'choice_label' => 'name',
                'required' => !$options['is_new'],
                'placeholder' => $options['is_new'] ? 'Automatisch aus Identifier erkennen' : 'Netzwerk wählen...',
                'label' => 'Netzwerk',
                'help' => $options['is_new']
                    ? 'Leer lassen — wird beim Speichern aus dem Identifier ermittelt. Nur bei Bedarf manuell wählen.'
                    : null,
                'query_builder' => fn (\Doctrine\ORM\EntityRepository $er) => $er->createQueryBuilder('n')->orderBy('n.name', 'ASC'),
            ])
            ->add('identifier', TextType::class, [
                'label' => 'Identifier',
                'help' => 'Vollständige URL des Profils (z. B. https://www.instagram.com/name/). Das Netzwerk wird daraus erkannt.',
            ])
            ->add('title', TextType::class, [
                'label' => 'Titel',
                'required' => false,
                'help' => 'Anzeigename für dieses Profil (optional)',
            ])
            ->add('autoFetch', CheckboxType::class, [
                'label' => 'Auto-Fetch',
                'required' => false,
            ])
            ->add('fetchSource', CheckboxType::class, [
                'label' => 'Quellcode einlesen',
                'required' => false,
            ])
            ->add('savePhotos', CheckboxType::class, [
                'label' => 'Fotos speichern',
                'required' => false,
            ])
            ->add('saveVideos', CheckboxType::class, [
                'label' => 'Videos speichern',
                'required' => false,
            ])
            ->add('transcribeVideos', CheckboxType::class, [
                'label' => 'Videos transkribieren',
                'required' => false,
                'help' => 'Heruntergeladene Videos automatisch per whisper.cpp transkribieren (benötigt „Videos speichern")',
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
