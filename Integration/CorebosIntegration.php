<?php

/*
 * @copyright   2021 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticCorebosBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;

class CorebosIntegration extends AbstractIntegration
{
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getName()
    {
        return 'Corebos';
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName()
    {
        return 'Corebos CRM';
    }

    /**
     * {@inheritdoc}
     */
    public function getIcon()
    {
        return 'plugins/MauticCorebosBundle/Assets/img/corebos.png';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredKeyFields()
    {
        return [
            'corebos_url' => 'mautic.integration.keyfield.corebos.url',
            'corebos_username' => 'mautic.integration.keyfield.corebos.username',
            'corebos_accesskey' => 'mautic.integration.keyfield.corebos.accesskey',
        ];
    }

    /**
     * @param FormBuilder|Form $builder
     * @param array            $data
     * @param string           $formArea
     */
    public function appendToForm(&$builder, $data, $formArea)
    {
        if ('keys' === $formArea) {
            $builder->add(
                'test_api',
                ButtonType::class,
                [
                    'label' => 'mautic.plugin.corebos.test_api',
                    'attr' => [
                        'class' => 'btn btn-primary',
                        'style' => 'margin-bottom: 10px',
                        'disabled' => 'disabled',
                        //'onclick' => 'Mautic.testCorebosConnection(this)',
                    ],
                ]
            );

            $builder->add(
                'stats',
                TextareaType::class,
                [
                    'label_attr' => ['class' => 'control-label'],
                    'label' => 'mautic.plugin.corebos.response',
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'rows' => '6',
                        'readonly' => 'readonly',
                    ],
                ]
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getAuthenticationType()
    {
        return 'none';
    }
}
