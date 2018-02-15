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
   * Fetches the name of the subsite parent and renders it as a link.
   *
   * {@inheritdoc}
   */
  public function build() {

    // Get the node from the current page route.
    $node = \Drupal::routeMatch()->getParameter('node');
    if ($node instanceof NodeInterface) {
      try {
        $subsite_parent_id = $node->get('field_s_subsite_ref')->getValue();
      }
      catch (Exception $e) {
        // If the item failed to get the reference then just return empty.
        return;
      }

      // If we have a reference field and it has a value, load up that node.
      if (is_numeric($subsite_parent_id[0]['target_id']) && $subsite_parent_id[0]['target_id'] > 0) {
        $subsite_parent_node = Node::load($subsite_parent_id[0]['target_id']);
      }
    }

    // If we found a subsite parent node build an <a> link and return it.
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
