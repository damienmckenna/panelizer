<?php
/**
 * @file
 * Class for the Panelizer node entity plugin.
 */

/**
 * Panelizer Entity node plugin class.
 *
 * Handles node specific functionality for Panelizer.
 */
class PanelizerEntityNode extends PanelizerEntityDefault {
  /**
   * True if the entity supports revisions.
   */
  public $supports_revisions = TRUE;
  public $entity_admin_root = 'admin/structure/types/manage/%panelizer_node_type';
  public $entity_admin_bundle = 4;

  public function entity_access($op, $entity) {
    // This must be implemented by the extending clas.
    return node_access($op, $entity);
  }

  /**
   * Implement the save function for the entity.
   */
  public function entity_save($entity) {
    node_save($entity);
  }

  public function entity_identifier($entity) {
    return t('This node');
  }

  public function entity_bundle_label() {
    return t('Node type');
  }

  /**
   * Implement the save function for the entity.
   */
  public function entity_allows_revisions($entity) {
    $retval = array();

    list($entity_id, $revision_id, $bundle) = entity_extract_ids($this->entity_type, $entity);

    $node_options = variable_get('node_options_' . $bundle, array('status', 'promote'));
    $retval[0] = in_array('revision', $node_options);
    $retval[1] = user_access('administer nodes');

    return $retval;
  }

  public function settings_form(&$form, &$form_state) {
    parent::settings_form($form, $form_state);

    $warn = FALSE;
    foreach ($this->plugin['bundles'] as $info) {
      if (!empty($info['status'])) {
        $warn = TRUE;
        break;
      }
    }

    if ($warn) {
      $task = page_manager_get_task('node_view');
      if (!empty($task['disabled'])) {
        drupal_set_message('The node template page is currently not enabled in page manager. You must enable this for Panelizer to be able to panelize nodes.', 'warning');
      }

      $handler = page_manager_load_task_handler($task, '', 'node_view_panelizer');
      if (!empty($handler->disabled)) {
        drupal_set_message('The panelizer variant on the node template page is currently not enabled in page manager. You must enable this for Panelizer to be able to panelize nodes.', 'warning');
      }
    }
  }

  function get_default_display() {
    $display = new panels_display;
    $display->layout = 'flexible';
    $display->layout_settings = array();
    $display->panel_settings = array(
      'style_settings' => array(
        'default' => NULL,
        'center' => NULL,
      ),
    );
    $display->cache = array();
    $display->title = '';
    $display->content = array();
    $display->panels = array();
    $pane = new stdClass;
    $pane->pid = 'new-1';
    $pane->panel = 'center';
    $pane->type = 'node_content';
    $pane->subtype = 'node_content';
    $pane->shown = TRUE;
    $pane->access = array();
    $pane->configuration = array(
      'links' => 1,
      'page' => 1,
      'no_extras' => 0,
      'override_title' => 0,
      'override_title_text' => '',
      'identifier' => '',
      'link' => 0,
      'leave_node_title' => 0,
      'context' => 'panelizer',
      'build_mode' => 'full',
    );
    $pane->cache = array();
    $pane->style = array(
      'settings' => NULL,
    );
    $pane->css = array();
    $pane->extras = array();
    $pane->position = 0;
    $display->content['new-1'] = $pane;
    if (module_exists('comment')) {
      $display->panels['center'][0] = 'new-1';
      $pane = new stdClass;
      $pane->pid = 'new-2';
      $pane->panel = 'center';
      $pane->type = 'node_comments';
      $pane->subtype = 'node_comments';
      $pane->shown = TRUE;
      $pane->access = array();
      $pane->configuration = array(
        'mode' => '4',
        'order' => '2',
        'comments_per_page' => '50',
        'context' => 'panelizer',
        'override_title' => 0,
        'override_title_text' => '',
      );
      $pane->cache = array();
      $pane->style = array(
        'settings' => NULL,
      );
      $pane->css = array();
      $pane->extras = array();
      $pane->position = 1;
      $display->content['new-2'] = $pane;
      $display->panels['center'][1] = 'new-2';
      $pane = new stdClass;
      $pane->pid = 'new-3';
      $pane->panel = 'center';
      $pane->type = 'node_comment_form';
      $pane->subtype = 'node_comment_form';
      $pane->shown = TRUE;
      $pane->access = array();
      $pane->configuration = array(
        'anon_links' => 1,
        'context' => 'panelizer',
        'override_title' => 0,
        'override_title_text' => '',
      );
      $pane->cache = array();
      $pane->style = array(
        'settings' => NULL,
      );
      $pane->css = array();
      $pane->extras = array();
      $pane->position = 2;
      $display->content['new-3'] = $pane;
    }

    $display->panels['center'][2] = 'new-3';
    $display->hide_title = PANELS_TITLE_FIXED;
    $display->title_pane = 'new-1';

    return $display;
  }


  /**
   * Implements a delegated hook_page_manager_handlers().
   *
   * This makes sure that all panelized entities have the proper entry
   * in page manager for rendering.
   */
  public function hook_default_page_manager_handlers(&$handlers) {
    $handler = new stdClass;
    $handler->disabled = FALSE; /* Edit this to true to make a default handler disabled initially */
    $handler->api_version = 1;
    $handler->name = 'node_view_panelizer';
    $handler->task = 'node_view';
    $handler->subtask = '';
    $handler->handler = 'panelizer_node';
    $handler->weight = -100;
    $handler->conf = array(
      'title' => t('Node panelizer'),
      'context' => 'argument_entity_id:node_1',
      'access' => array(),
    );
    $handlers['node_view_panelizer'] = $handler;

    return $handlers;
  }

  /**
   * Implements a delegated hook_form_alter.
   *
   * We want to add Panelizer settings for the bundle to the node type form.
   */
  public function hook_form_alter(&$form, &$form_state, $form_id) {
    if ($form_id == 'node_type_form') {
      if (isset($form['#node_type'])) {
        $bundle = $form['#node_type']->type;
        $this->add_bundle_setting_form($form, $form_state, $bundle, array('type'));
      }
    }
  }

  public function hook_page_alter(&$page) {
    if ($_GET['q'] == 'admin/structure/types' && !empty($page['content']['system_main']['node_table'])) {
      // shortcut
      $table = &$page['content']['system_main']['node_table'];
      // Modify the header.
      $table['#header'][1]['colspan'] = 5;

      // Since we can't tell what row a type is for, but we know that they
      // were generated in this order, go through the original types
      // list:
      $types = node_type_get_types();
      $names = node_type_get_names();
      $row_index = 0;
      foreach ($names as $bundle => $name) {
        $type = $types[$bundle];
        if (node_hook($type->type, 'form')) {
          $type_url_str = str_replace('_', '-', $type->type);
          if ($this->is_panelized($bundle) && panelizer_administer_entity_bundle($this, $bundle)) {
            $table['#rows'][$row_index][] = array('data' => l(t('panelizer'), 'admin/structure/types/manage/' . $type_url_str . '/panelizer'));
          }
          else {
            $table['#rows'][$row_index][] = array('data' => '');
          }
          // Update row index for next pass:
          $row_index++;
        }
      }
    }
  }

}
