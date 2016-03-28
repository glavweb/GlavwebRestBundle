<?php

namespace Glavweb\RestBundle\Form;

use Glavweb\RestBundle\Security\EditableRolesBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;

/**
 * Class SecurityRolesType
 * @package Glavweb\RestBundle\Form
 */
class SecurityRolesType extends AbstractType
{
    /**
     * @var EditableRolesBuilder
     */
    protected $rolesBuilder;

    /**
     * @param EditableRolesBuilder $rolesBuilder
     */
    public function __construct(EditableRolesBuilder $rolesBuilder)
    {
        $this->rolesBuilder = $rolesBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $attr = $view->vars['attr'];

        if (isset($attr['class']) && empty($attr['class'])) {
            $attr['class'] = 'sonata-medium';
        }

        $view->vars['entityRoles']   = $options['entityRoles'];
        $view->vars['securityRoles'] = $options['securityRoles'];
        $view->vars['attr']          = $attr;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        list($entityRoles, $securityRoles) = $this->rolesBuilder->getRoles();

        $resolver->setDefaults(array(
            'choices' => function (Options $options, $parentChoices) use ($entityRoles, $securityRoles) {
                return empty($parentChoices) ? array_merge($entityRoles, $securityRoles) : array();
            },

            'entityRoles' => function (Options $options, $parentChoices) use ($entityRoles) {
                return empty($parentChoices) ? $entityRoles : array();
            },

            'securityRoles' => function (Options $options, $parentChoices) use ($securityRoles) {
                return empty($parentChoices) ? $securityRoles : array();
            },

            'data_class' => null
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'choice';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'glavweb_security_roles';
    }
}