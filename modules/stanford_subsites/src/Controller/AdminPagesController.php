<?php
/**
 * @file
 * Stanford CAPx administration pages.
 */

namespace Drupal\stanford_subsites\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for page example routes.
 */
class AdminPagesController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  protected function getModuleName() {
    return 'stanford_subsites';
  }

  /**
   * Athentication Credentials Input Page.
   *
   * @return [type] [description]
   */
  public function settingsPage() {
    $content = "<h1>Hi</h1>";
    return [
      '#markup' => $content,
    ];
  }

}
