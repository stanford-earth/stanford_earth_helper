<?php

namespace Drupal\stanford_subsites\Plugin\Block;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Provides a block with a simple text.
 *
 * @Block(
 *   id = "stanford_subsites_parent_name",
 *   admin_label = @Translation("Subsite Parent Label"),
 *   category = @Translation("stanford_subsites")
 * )
 */
class SubsiteParentName extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      $subsite_parent_id = $node->get('field_s_subsite_ref')->getValue();
      if (is_numeric($subsite_parent_id[0]['target_id']) && $subsite_parent_id[0]['target_id'] > 0) {
        $subsite_parent_node = Node::load($subsite_parent_id[0]['target_id']);
      }
    }

    if (!empty($subsite_parent_node)) {
      $link = $subsite_parent_node->toLink();
      return $link->toRenderable();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

}
