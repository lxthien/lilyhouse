<?php

namespace AppBundle\Form;

use AppBundle\Entity\ProductCat;
use AppBundle\Form\Type\TagsInputType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProductCatType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('parentcat', null, [
                'attr' => ['autofocus' => true],
                'label' => 'label.parentcat',
            ])
            ->add('name', TextType::class, [
                'attr' => ['class' => 'sluggable'],
                'label' => 'label.name',
            ])
            ->add('url', TextType::class, [
                'attr' => ['class' => 'url', 'readonly' => 'readonly'],
                'label' => 'label.url',
            ])
            ->add('description', TextareaType::class, [
                'attr' => ['class' => 'txt-ckeditor', 'data-height' => '300'],
                'label' => 'label.description',
            ])
            ->add('enable', CheckboxType::class, [
                'required' => false,
                'label' => 'label.enable',
            ])
            ->add('pageTitle', TextType::class, [
                'required' => false,
                'label' => 'label.pageTitle',
            ])
            ->add('pageDescription', TextareaType::class, [
                'required' => false,
                'label' => 'label.pageDescription',
            ])
            ->add('pageKeyword', TextType::class, [
                'required' => false,
                'label' => 'label.pageKeyword',
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ProductCat::class,
        ]);
    }
}
