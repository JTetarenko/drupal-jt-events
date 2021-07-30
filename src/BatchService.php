<?php

namespace Drupal\jt_events;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

/**
 * Class BatchService
 *
 * @package Drupal\jt_events
 */
class BatchService {

  use StringTranslationTrait;

  /**
   * @param $id
   * @param $event
   * @param $operation_details
   * @param $context
   */
  public function processEvent($id, $event, $operation_details, &$context) {
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $term_id = !$event->field_event_type->isEmpty()
      ? $event->get('field_event_type')->getValue()[0]['target_id']
      : null;

    $params = ['context' => []];

    $params['context']['subject'] = t('Notification about event');

    $params['context']['message'] = t('<h1>@name</h1><p>Event type: @type</p><p>Event date: @date</p><p>Description: @desc</p>', [
      '@name' => $event->getTitle(),
      '@type' => !is_null($term_id) ? Term::load($term_id)->get('name')->value : $term_id,
      '@date' => $event->get('field_event_date')->value,
      '@desc' => $event->get('field_event_description')->value,
    ]);

    if (!$event->field_event_image->isEmpty()) {
      $image_field = $event->get('field_event_image')->getValue();
      $media_id = Media::load($image_field[0]['target_id']);
      $image = File::load($media_id->id());
      $params['attachments'][] = $image;
    }

    $participants = $event->get('field_event_participants')->getValue();

    $emails = [];

    foreach($participants as $participant) {
      $account = User::load($participant['target_id']);
      $emails[] = $account->getEmail();
    }

    $to = implode(',', $emails);

    $mailManager = \Drupal::service('plugin.manager.mail');

    $mailManager->mail('jt_events', 'mail', $to, $langcode, $params);

    $context['results'][] = $id;
    // Optional message displayed under the progressbar.
    $context['message'] = t('Running Batch "@id" @details', [
      '@id' => $id,
      '@details' => $operation_details,
    ]);
  }

  /**
   * Batch Finished callback.
   *
   * @param bool $success
   *   Success of the operation.
   * @param array $results
   *   Array of results for post processing.
   * @param array $operations
   *   Array of operations.
   */
  public function processEventFinished($success, array $results, array $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('@count results processed.', [
        '@count' => count($results),
      ]));
    }
    else {
      $error_operation = reset($operations);
      \Drupal::messenger()->addMessage(t('An error occurred while processing @operation with arguments : @args', [
        '@operation' => $error_operation[0],
        '@args' => print_r($error_operation[0], TRUE),
      ]));
    }
  }

}
