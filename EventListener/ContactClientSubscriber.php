<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactClientBundle\EventListener;

use DateTime;
use Doctrine\ORM\EntityManager;
use Exception;
use Mautic\AssetBundle\Helper\TokenHelper as AssetTokenHelper;
use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\Entity\LeadEventLogRepository;
use Mautic\CampaignBundle\Event\ScheduledEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use Mautic\FormBundle\Helper\TokenHelper as FormTokenHelper;
use Mautic\PageBundle\Helper\TokenHelper as PageTokenHelper;
use Mautic\PageBundle\Model\PageModel;
use Mautic\PageBundle\Model\TrackableModel;
use MauticPlugin\MauticContactClientBundle\ContactClientEvents;
use MauticPlugin\MauticContactClientBundle\Entity\EventRepository;
use MauticPlugin\MauticContactClientBundle\Entity\Stat;
use MauticPlugin\MauticContactClientBundle\Event\ContactClientEvent;
use MauticPlugin\MauticContactClientBundle\Event\ContactClientTimelineEvent;
use MauticPlugin\MauticContactClientBundle\Model\ContactClientModel;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class ContactClientSubscriber.
 */
class ContactClientSubscriber implements EventSubscriberInterface
{
    /** @var Session */
    public $session;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var IpLookupHelper
     */
    protected $ipHelper;

    /**
     * @var AuditLogModel
     */
    protected $auditLogModel;

    /**
     * @var TrackableModel
     */
    protected $trackableModel;

    /**
     * @var PageTokenHelper
     */
    protected $pageTokenHelper;

    /**
     * @var AssetTokenHelper
     */
    protected $assetTokenHelper;

    /**
     * @var FormTokenHelper
     */
    protected $formTokenHelper;

    /**
     * @var ContactClientModel
     */
    protected $contactclientModel;

    /** @var PageModel */
    protected $pageModel;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * ContactClientSubscriber constructor.
     *
     * @param RouterInterface    $router
     * @param IpLookupHelper     $ipLookupHelper
     * @param AuditLogModel      $auditLogModel
     * @param TrackableModel     $trackableModel
     * @param PageTokenHelper    $pageTokenHelper
     * @param AssetTokenHelper   $assetTokenHelper
     * @param FormTokenHelper    $formTokenHelper
     * @param ContactClientModel $contactclientModel
     * @param Session            $session
     * @param EntityManager      $em
     */
    public function __construct(
        RouterInterface $router,
        IpLookupHelper $ipLookupHelper,
        AuditLogModel $auditLogModel,
        TrackableModel $trackableModel,
        PageTokenHelper $pageTokenHelper,
        AssetTokenHelper $assetTokenHelper,
        FormTokenHelper $formTokenHelper,
        ContactClientModel $contactclientModel,
        Session $session,
        EntityManager $em
    ) {
        $this->router             = $router;
        $this->ipHelper           = $ipLookupHelper;
        $this->auditLogModel      = $auditLogModel;
        $this->trackableModel     = $trackableModel;
        $this->pageTokenHelper    = $pageTokenHelper;
        $this->assetTokenHelper   = $assetTokenHelper;
        $this->formTokenHelper    = $formTokenHelper;
        $this->contactclientModel = $contactclientModel;
        $this->session            = $session;
        $this->em                 = $em;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ContactClientEvents::POST_SAVE            => ['onContactClientPostSave', 0],
            ContactClientEvents::POST_DELETE          => ['onContactClientDelete', 0],
            ContactClientEvents::TIMELINE_ON_GENERATE => ['onTimelineGenerate', 0],
            CampaignEvents::ON_EVENT_SCHEDULED        => ['onEventScheduled', 0],
        ];
    }

    /**
     * Add an entry to the audit log.
     *
     * @param ContactClientEvent $event
     */
    public function onContactClientPostSave(ContactClientEvent $event)
    {
        $entity = $event->getContactClient();
        if ($details = $event->getChanges()) {
            $log = [
                'bundle'    => 'contactclient',
                'object'    => 'contactclient',
                'objectId'  => $entity->getId(),
                'action'    => ($event->isNew()) ? 'create' : 'update',
                'details'   => $details,
                'ipAddress' => $this->ipHelper->getIpAddressFromRequest(),
            ];
            $this->auditLogModel->writeToLog($log);
        }
    }

    /**
     * Add a delete entry to the audit log.
     *
     * @param ContactClientEvent $event
     */
    public function onContactClientDelete(ContactClientEvent $event)
    {
        $entity = $event->getContactClient();
        $log    = [
            'bundle'    => 'contactclient',
            'object'    => 'contactclient',
            'objectId'  => $entity->deletedId,
            'action'    => 'delete',
            'details'   => ['name' => $entity->getName()],
            'ipAddress' => $this->ipHelper->getIpAddressFromRequest(),
        ];
        $this->auditLogModel->writeToLog($log);
    }

    /**
     * Compile events for the lead timeline.
     *
     * @param ContactClientTransactionsEvent $event
     */
    public function onTimelineGenerate(ContactClientTimelineEvent $event)
    {
        /** @var EventRepository $eventRepository */
        $eventRepository = $this->getEntityManager()->getRepository('MauticContactClientBundle:Event');

        // Set available event types
        // $event->addSerializerGroup(['formList', 'submissionEventDetails']);
        foreach (Stat::getAllTypes() as $type) {
            // TODO: $event->addEventType($type, $this->translator->trans('mautic.contactclient.event.'.$type));
            $event->addEventType($type, ucfirst($type));
        }
        $results = $eventRepository->getEventsForTimeline(
            $event->getContactClient()->getId(),
            $event->getQueryOptions()
        );

        $rows  = isset($results['results']) ? $results['results'] : $results;
        $total = isset($results['total']) ? $results['total'] : count($rows);

        foreach ($rows as $row) {
            $eventTypeKey  = $row['type'];
            $eventTypeName = ucwords($eventTypeKey);

            // Add total to counter
            $event->setQueryTotal($total);
            $event->addToCounter($eventTypeKey, 1);

            $log = '{' === $row['logs'][0] ? json_encode(json_decode($row['logs']), JSON_PRETTY_PRINT) : $row['logs'];

            if (!$event->isEngagementCount()) {
                $event->addEvent(
                    [
                        'event'           => $eventTypeKey,
                        'eventId'         => $eventTypeKey.$row['id'],
                        'eventLabel'      => [
                            'label' => $eventTypeName,
                            'href'  => $this->router->generate(
                                'mautic_form_action',
                                ['objectAction' => 'view', 'objectId' => $row['id']]
                            ),
                        ],
                        'eventType'       => $eventTypeName,
                        'extra'           => [
                            'logs'                => strip_tags($log),
                            'integrationEntityId' => $row['integration_entity_id'],
                        ],
                        'contentTemplate' => 'MauticContactClientBundle:Transactions:eventdetails.html.php',
                        'icon'            => 'fa-plus-square-o',
                        'message'         => $row['message'],
                        'contactId'       => $row['contact_id'],
                        'utmSource'       => $row['utm_source'],
                        'timestamp'       => $row['date_added'],
                    ]
                );
            }
        }
    }

    /**
     * Shore up EntityManager loading, in case there is a flaw in a plugin or campaign handling.
     *
     * @return EntityManager
     */
    private function getEntityManager()
    {
        try {
            if ($this->em && !$this->em->isOpen()) {
                $this->em = $this->em->create(
                    $this->em->getConnection(),
                    $this->em->getConfiguration(),
                    $this->em->getEventManager()
                );
                $this->logger->error('ContactClient: EntityManager was closed.');
            }
        } catch (Exception $exception) {
            $this->logger->error('ContactClient: EntityManager could not be reopened.');
        }

        return $this->em;
    }

    /**
     * @param ScheduledEvent $event
     */
    public function onEventScheduled(ScheduledEvent $event)
    {
        if ($event->isReschedule()) {
            /** @var LeadEventLog $log */
            $log   = $event->getLog();
            $logId = $log->getId();

            // do this when a LeadEventLog is meant to be rescheduled
            $events = $this->session->get('contact.client.reschedule.event');

            if (!empty($events) && isset($events[$logId])) {
                if ($events[$logId] instanceof DateTime) {
                    // For BC.
                    $triggerDate = $events[$logId];
                    $reason      = null;
                } else {
                    $triggerDate = isset($events[$logId]['triggerDate']) ? $events[$logId]['triggerDate'] : null;
                    $reason      = isset($events[$logId]['reason']) ? $events[$logId]['reason'] : null;
                }
                if (
                    $triggerDate
                    && ($log->getTriggerDate() !== $triggerDate || !$log->getIsScheduled())
                ) {
                    $log->setTriggerDate($triggerDate);
                    $log->setIsScheduled(true);

                    if ($reason) {
                        // Persist to the metadata of the event log (faster than a secondary entity).
                        $log->appendToMetadata(['reason' => $reason]);
                    }

                    /** @var LeadEventLogRepository $leadEventLogRepo */
                    $leadEventLogRepo = $this->getEntityManager()->getRepository('MauticCampaignBundle:LeadEventLog');
                    $leadEventLogRepo->saveEntity($log);
                }

                unset($events[$logId]);
                $this->session->set('contact.client.reschedule.event', $events);
            }
        }
    }
}
