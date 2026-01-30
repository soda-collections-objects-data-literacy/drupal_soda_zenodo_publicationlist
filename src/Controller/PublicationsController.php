<?php

namespace Drupal\sodanodo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

/**
 * Class PublicationsController.
 */
class PublicationsController extends ControllerBase {

  /**
   * Displays the publications list.
   */
  public function list() {
    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['type' => 'zenodo_publications_page']);

    $node = reset($node);

    if ($node) {
      return [
        '#markup' => $node->get('field_publications_html')->value,
      ];
    }

    return ['#markup' => $this->t('No publications found.')];
  }
}
