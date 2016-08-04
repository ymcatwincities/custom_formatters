<?php

namespace Drupal\custom_formatters\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\custom_formatters\FormatterInterface;

/**
 * Defines the formatter entity.
 *
 * @ConfigEntityType(
 *   id = "formatter",
 *   label = @Translation("Formatter"),
 *   handlers = {
 *     "access" = "Drupal\custom_formatters\FormatterAccessControlHandler",
 *     "list_builder" = "Drupal\custom_formatters\FormatterListBuilder",
 *     "form" = {
 *       "default" = "Drupal\custom_formatters\Form\FormatterForm",
 *       "edit" = "Drupal\custom_formatters\Form\FormatterForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "formatter",
 *   admin_permission = "administer custom formatters",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "delete-form" = "/admin/structure/formatters/manage/{custom_formatter}/delete",
 *     "edit-form" = "/admin/structure/formatters/manage/{custom_formatter}",
 *     "collection" = "/admin/structure/formatters",
 *   }
 * )
 */
class Formatter extends ConfigEntityBase implements FormatterInterface {

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    /** @var \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager */
    $field_type_manager = \Drupal::service('plugin.manager.field.field_type');
    $field_type_definitions = $field_type_manager->getDefinitions();
    /** @var string $field_type */
    foreach ($this->field_types as $field_type) {
      if (isset($field_type_definitions[$field_type])) {
        $this->addDependency('module', $field_type_definitions[$field_type]['provider']);
      }
    }

    // Allow formatter type plugins a chance to add dependencies.
    $dependencies = $this->getFormatterType()->calculateDependencies();
    if (!empty($dependencies) && is_array($dependencies)) {
      foreach ($dependencies as $type => $type_dependencies) {
        if (!empty($type_dependencies) && is_array($type_dependencies)) {
          foreach ($type_dependencies as $name) {
            $this->addDependency($type, $name);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormatterType() {
    /** @var \Drupal\custom_formatters\FormatterTypeManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.custom_formatters.formatter_type');

    // Ensure Formatter Type exists.
    if (!isset($plugin_manager->getDefinitions()[$this->get('type')])) {
      // @TODO - Add better error handling here.
      return FALSE;
    }

    return $plugin_manager->createInstance($this->get('type'), ['entity' => $this]);
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
//    $field_types = drupal_explode_tags($formatter->field_types);
//    $field_info = field_info_fields();
//
//    foreach (field_info_instances() as $bundles) {
//      foreach ($bundles as $fields) {
//        foreach ($fields as $field) {
//          if (in_array($field_info[$field['field_name']]['type'], $field_types)) {
//            foreach ($field['display'] as $display) {
//              if ($display['type'] == "custom_formatters_{$formatter->name}") {
//                return TRUE;
//              }
//            }
//          }
//        }
//      }
//    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function postLoad(EntityStorageInterface $storage, array &$entities) {
    /** @var \Drupal\custom_formatters\FormatterInterface $entity */
    foreach ($entities as $entity) {
      $entity->getFormatterType()->postLoad();
    }
    parent::postLoad($storage, $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    $this->getFormatterType()->preSave();
  }

  /**
   * {@inheritdoc}
   */
  protected function invalidateTagsOnSave($update) {
    // An entity was created or updated: invalidate its list cache tags. (An
    // updated entity may start to appear in a listing because it now meets that
    // listing's filtering requirements. A newly created entity may start to
    // appear in listings because it did not exist before).
    /** @var array $tags */
    $tags = $this->getEntityType()->getListCacheTags();
    if ($update) {
      // An existing entity was updated, also invalidate its unique cache tag.
      $tags = Cache::mergeTags($tags, $this->getCacheTagsToInvalidate());
    }
    Cache::invalidateTags($tags);
  }

}
