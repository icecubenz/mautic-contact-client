<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

return [
    'name'        => 'Contact Client',
    'description' => 'Send contacts to third party APIs or enhance your contacts without code.',
    'version'     => '2.15.1',
    'author'      => 'Mautic',

    'routes' => [
        'main' => [
            'mautic_contactclient_index' => [
                'path'       => '/contactclient/{page}',
                'controller' => 'MauticContactClientBundle:ContactClient:index',
            ],
            'mautic_contactclient_action' => [
                'path'         => '/contactclient/{objectAction}/{objectId}',
                'controller'   => 'MauticContactClientBundle:ContactClient:execute',
                'requirements' => [
                    'objectAction' => '\w+',
                    'objectId'     => '\w+',
                ],
            ],
            'mautic_contactclient_transactions' => [
                'path'         => '/contactclient/view/{objectId}/transactions/{page}',
                'controller'   => 'MauticContactClientBundle:Ajax:transaction',
                'requirements' => [
                    'objectId' => '\d+',
                    'page'     => '\d+',
                ],
            ],
            'mautic_contactclient_transactions_search' => [
                'path'         => '/contactclient/view/{objectId}/transactions/search',
                'controller'   => 'MauticContactClientBundle:Transactions:index',
                'requirements' => [
                    'objectId' => '\d+',
                ],
            ],
            'mautic_contactclient_transactions_export' => [
                'path'         => '/contactclient/view/{objectId}/transactions/export',
                'controller'   => 'MauticContactClientBundle:Transactions:export',
                'requirements' => [
                    'objectId' => '\d+',
                ],
            ],
            'mautic_contactclient_files' => [
                'path'         => '/contactclient/view/{objectId}/files/{page}',
                'controller'   => 'MauticContactClientBundle:Files:index',
                'requirements' => [
                    'objectId' => '\d+',
                    'page'     => '\d+',
                ],
            ],
            'mautic_contactclient_files_file' => [
                'path'         => '/contactclient/view/{objectId}/files/file/{fileId}',
                'controller'   => 'MauticContactClientBundle:Files:file',
                'requirements' => [
                    'objectId' => '\d+',
                    'fileId'   => '\d+',
                ],
            ],
        ],
    ],

    'services' => [
        'events'       => [
            'mautic.contactclient.subscriber.stat' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\EventListener\StatSubscriber::class,
                'arguments' => [
                    'mautic.contactclient.model.contactclient',
                ],
            ],
            'mautic.contactclient.subscriber.lead_timeline' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\EventListener\LeadTimelineSubscriber::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.contactclient.subscriber.contactclient' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\EventListener\ContactClientSubscriber::class,
                'arguments' => [
                    'router',
                    'mautic.helper.ip_lookup',
                    'mautic.core.model.auditlog',
                    'mautic.page.model.trackable',
                    'mautic.page.helper.token',
                    'mautic.asset.helper.token',
                    'mautic.form.helper.token',
                    'mautic.contactclient.model.contactclient',
                    'session',
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.contactclient.stats.subscriber' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\EventListener\StatsSubscriber::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.contactclient.search.subscriber' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\EventListener\SearchSubscriber::class,
                'arguments' => [
                    'mautic.contactclient.model.contactclient',
                ],
            ],
        ],
        'forms'        => [
            'mautic.contactclient.form.type.contactclientshow_list' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Form\Type\ContactClientShowType::class,
                'arguments' => 'router',
                'alias'     => 'contactclientshow_list',
            ],
            'mautic.contactclient.form.type.contactclient_list' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Form\Type\ContactClientListType::class,
                'arguments' => 'mautic.contactclient.model.contactclient',
                'alias'     => 'contactclient_list',
            ],
            'mautic.contactclient.form.type.contactclient' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Form\Type\ContactClientType::class,
                'alias'     => 'contactclient',
                'arguments' => [
                    'mautic.security',
                    'mautic.lead.model.lead',
                ],
            ],
            'mautic.contactclient.form.type.chartfilter' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Form\Type\ChartFilterType::class,
                'arguments' => 'mautic.factory',
                'alias'     => 'contactclient_chart',
            ],
        ],
        'models'       => [
            'mautic.contactclient.model.contactclient' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Model\ContactClientModel::class,
                'arguments' => [
                    'mautic.form.model.form',
                    'mautic.page.model.trackable',
                    'mautic.helper.templating',
                    'event_dispatcher',
                    'mautic.lead.model.lead',
                ],
            ],
            'mautic.contactclient.model.apipayload' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Model\ApiPayload::class,
                'arguments' => [
                    'mautic.contactclient.model.contactclient',
                    'mautic.contactclient.service.transport',
                    'mautic.contactclient.helper.token',
                    'mautic.contactclient.model.schedule',
                    'mautic.contactclient.model.apipayloadauth',
                ],
            ],
            'mautic.contactclient.model.apipayloadauth' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Model\ApiPayloadAuth::class,
                'arguments' => [
                    'mautic.contactclient.helper.token',
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.contactclient.model.filepayload' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Model\FilePayload::class,
                'arguments' => [
                    'mautic.contactclient.model.contactclient',
                    'mautic.contactclient.helper.token',
                    'doctrine.orm.entity_manager',
                    'mautic.core.model.form',
                    'mautic.campaign.model.event',
                    'mautic.lead.model.lead',
                    'mautic.helper.paths',
                    'mautic.helper.core_parameters',
                    'symfony.filesystem',
                    'mautic.helper.mailer',
                    'mautic.contactclient.model.schedule',
                    'mautic.contactclient.helper.utmsource',
                ],
            ],
            'mautic.contactclient.model.cache' => [
                'class' => \MauticPlugin\MauticContactClientBundle\Model\Cache::class,
            ],
            'mautic.contactclient.model.schedule' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Model\Schedule::class,
                'arguments' => [
                    'doctrine.orm.default_entity_manager',
                    'mautic.helper.core_parameters',
                ],
            ],
        ],
        'integrations' => [
            'mautic.contactclient.integration' => [
                'class' => \MauticPlugin\MauticContactClientBundle\Integration\ClientIntegration::class,
            ],
        ],
        'other'        => [
            'mautic.contactclient.helper.token' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Helper\TokenHelper::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                    'monolog.logger.mautic',
                    'mautic.lead.model.lead',
                    'mautic.contactclient.helper.utmsource',
                ],
            ],
            'mautic.contactclient.helper.utmsource' => [
                'class' => \MauticPlugin\MauticContactClientBundle\Helper\UtmSourceHelper::class,
            ],
            'mautic.contactclient.guzzle.client' => [
                'class' => \GuzzleHttp\Client::class,
            ],
            'mautic.contactclient.service.transport' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Services\Transport::class,
                'arguments' => [
                    'mautic.contactclient.guzzle.client',
                ],
            ],
            'mautic.contactclient.helper.client_event' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Helper\ClientEventHelper::class,
                'arguments' => [
                    'mautic.campaign.repository.campaign',
                    'mautic.campaign.repository.lead_event_log',
                    'mautic.campaign.repository.event',
                    'doctrine.orm.entity_manager',
                ],
            ],
        ],

        'integrations' => [
            'mautic.integration.client' => [
                'class'     => \MauticPlugin\MauticContactClientBundle\Integration\ClientIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'session',
                    'request_stack',
                    'router',
                    'translator',
                    'logger',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                ],
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'mautic.contactclient' => [
                'route'     => 'mautic_contactclient_index',
                'access'    => 'contactclient:items:view',
                'id'        => 'mautic_contactclient_root',
                'iconClass' => 'fa-cloud-upload',
                'priority'  => 35,
                'checks'    => [
                    'integration' => [
                        'Client' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
        ],
    ],

    'categories' => [
        'plugin:contactclient' => 'mautic.contactclient',
    ],
];
