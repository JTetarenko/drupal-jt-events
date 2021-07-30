<?php

namespace Drupal\jt_events\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;

/**
 * Class JtEventsCommands
 *
 * @package Drupal\jt_events\Commands
 */
class JtEventsCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var LoggerChannelFactoryInterface
   */
  protected $loggerChannelFactory;

  /**
   * EventsCommands constructor.
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   * @param LoggerChannelFactoryInterface $loggerChannelFactory
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager,
                              LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerChannelFactory = $loggerChannelFactory;
  }

  /**
   * Go through all events and notify participants by sending mail messages
   *
   * @usage jt_events_commands:notifyParticipants
   * @command jt_events_commands:notifyParticipants
   * @aliases notify-participants np
   */
  public function notifyParticipants() {
    $this->loggerChannelFactory->get('jt_events:commands:notify-participants')->info($this->t('Email sending to participants started'));

    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $query = $storage->getQuery()
        ->condition('type', 'event')
        ->condition('status', '1');
      $nids = $query->execute();
    }
    catch (\Exception $e) {
      $this->output()->writeln($e);
      $this->loggerChannelFactory->get('jt_events:commands:notify-participants')->warning($this->t('Error found @e', ['@e' => $e,]));
    }

    $operations = [];
    $numOperations = 0;
    $batchId = 1;
    if (!empty($nids)) {
      foreach ($nids as $nid) {
        $entity = $storage->load($nid);
        $this->output()->writeln($this->t('Preparing batch: ') . $batchId);
        $operations[] = [
          '\Drupal\jt_events\BatchService::processEvent', [
            $batchId,
            $entity,
            $this->t('Processing node @nid', ['@nid' => $nid,]),
          ],
        ];
        $batchId++;
        $numOperations++;
      }
    }
    else {
      $this->logger()->warning($this->t('No nodes of type events found'));
    }

    $batch = [
      'title' => $this->t('Iterating @num node(s)', ['@num' => $numOperations,]),
      'operations' => $operations,
      'finished' => '\Drupal\jt_events\BatchService::processEventFinished',
    ];

    batch_set($batch);

    drush_backend_batch_process();

    $this->logger()->notice($this->t('Batch operations end.'));

    $this->loggerChannelFactory->get('jt_events:commands:notify-participants')->info($this->t('Email sending to participants finished'));
  }
}
