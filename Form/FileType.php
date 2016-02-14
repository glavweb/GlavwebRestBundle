<?php

namespace Glavweb\RestBundle\Form;

use AppBundle\Form\SimpleDocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class FileType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', null, array(
                'property_path' => $options['property_path']
            ))
        ;
    }
    
    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'allow_extra_fields' => true
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'file';
    }
}
