<?php

namespace Drupal\stanford_subsites\Plugin\Block;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\system\Entity\Menu;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Link;

/**
 * Provides a block with a simple text.
 *
 * @Block(
 *   id = "stanford_subsites_menu_block",
 *   admin_label = @Translation("Subsite Dynamic Menu Block"),
 *   category = @Translation("stanford_subsites")
 * )
 */
class SubsiteMenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $node = \Drupal::routeMatch()->getParameter('node');
    $subsite_parent_id = $node->get('field_s_subsite_ref')->getValue();

    // If node is the subsite parent itself.
    if (
      !empty($node->get('field_s_subsite_ref')) &&
      $node->getType() == "stanford_subsite" &&
      empty($subsite_parent_id[0]['target_id'])
    ) {
      $subsite_parent_node = $node;
    }

    // If node has a reference to a subsite use that reference.
    if (
      isset($subsite_parent_id[0]['target_id']) &&
      is_numeric($subsite_parent_id[0]['target_id']) &&
      $subsite_parent_id[0]['target_id'] > 0
    ) {
      $subsite_parent_node = Node::load($subsite_parent_id[0]['target_id']);
    }

    // No node... No problems.
    if (empty($subsite_parent_node)) {
      return;
    }

    $menu_name = stanford_subsites_get_menu_name_from_subsite_entity($subsite_parent_node);
    $menu_tree = \Drupal::menuTree();

    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);
    $tree = $menu_tree->load($menu_name, $parameters);
    $manipulators = array(
      // Only show links that are accessible for the current user.
      array('callable' => 'menu.default_tree_manipulators:checkAccess'),
      // Use the default sorting of menu links.
      array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
    );
    $tree = $menu_tree->transform($tree, $manipulators);
    $menu = $menu_tree->build($tree);

    return array('#markup' => drupal_render($menu));
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

}
