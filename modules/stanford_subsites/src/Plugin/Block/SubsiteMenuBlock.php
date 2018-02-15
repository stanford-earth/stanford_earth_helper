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
 * Provides a block with the correct subsite's menu.
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

    // Fetch the currently being viewed node as it will have all the information
    // we will need to determine which menu to load.
    $node = \Drupal::routeMatch()->getParameter('node');
    try {
      $subsite_parent_id = $node->get('field_s_subsite_ref')->getValue();
    }
    catch (Exception $e) {
      // If something went wrong then we probably don't have a subsite.
      return;
    }

    // If node is the subsite parent itself.
    if (
      !empty($node->get('field_s_subsite_ref')) &&
      $node->getType() == "stanford_subsite" &&
      empty($subsite_parent_id[0]['target_id'])
    ) {
      $subsite_parent_node = $node;
    }

    // If node has a reference to a subsite, use that reference.
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

    // Fetch the menu name to load from the successfully loaded
    // subsite parent entity.
    $menu_name = stanford_subsites_get_menu_name_from_subsite_entity($subsite_parent_node);

    // Load up and create the menu tree.
    $menu_tree = \Drupal::menuTree();

    // Some menu settings to tweak first.
    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);
    $parameters->onlyEnabledLinks();
    $manipulators = array(
      // Only show links that are accessible for the current user.
      array('callable' => 'menu.default_tree_manipulators:checkAccess'),
      // Use the default sorting of menu links.
      array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
    );

    // Load and build.
    $tree = $menu_tree->load($menu_name, $parameters);
    $tree = $menu_tree->transform($tree, $manipulators);
    $menu = $menu_tree->build($tree);

    // This might be ok as a render array some day.
    return array('#markup' => drupal_render($menu));
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

}
